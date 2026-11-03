<?php

namespace App\Http\Controllers;

use App\Import\CsvReader;
use App\Import\ImportPlanner;
use App\Jobs\RunImport;
use App\Models\ImportJob;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const array HEADER_HINTS = [
        'date' => ['date', 'datum', 'fecha', 'booking', 'posted', 'transaction date', 'value date'],
        'description' => ['description', 'omschrijving', 'memo', 'details', 'narration', 'particulars', 'reference text', 'purpose'],
        'amount' => ['amount', 'bedrag', 'betrag', 'value', 'sum'],
        'debit' => ['debit', 'withdrawal', 'paid out', 'money out', 'af'],
        'credit' => ['credit', 'deposit', 'paid in', 'money in', 'bij'],
        'counterparty' => ['payee', 'counterparty', 'merchant', 'name', 'tegenpartij', 'beneficiary', 'recipient'],
        'category' => ['category', 'categorie'],
        'notes' => ['notes', 'note', 'comment'],
        'tags' => ['tags', 'labels'],
        'external_id' => ['transaction id', 'reference', 'ref', 'id'],
    ];

    public function __construct(private CsvReader $csv, private ImportPlanner $planner) {}

    public function index(Ledger $ledger): JsonResponse
    {
        return response()->json([
            'data' => $ledger->importJobs()->latest()->limit(20)->get()->map(fn (ImportJob $job) => $this->present($job)),
            'profiles' => $ledger->importProfiles()->orderBy('name')->get(['id', 'name', 'config']),
        ]);
    }

    public function store(Request $request, Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config()->integer('ledgerfly.attachments.max_size_kb'), 'mimes:csv,txt,xml,tsv'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $format = Str::lower($file->getClientOriginalExtension()) === 'xml' ? 'camt053' : 'csv';
        $path = $file->store("imports/{$ledger->id}", config()->string('ledgerfly.attachments.disk'));
        abort_if($path === false, 500);

        $job = $ledger->importJobs()->create([
            'user_id' => $user->id,
            'format' => $format,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'status' => 'uploaded',
        ]);

        return response()->json(['data' => $this->present($job, withSample: true)], Response::HTTP_CREATED);
    }

    /**
     * An empty CSV with the columns LedgerFly maps automatically. The ledger is in the
     * signature so the route's ledger permission check receives the bound model.
     */
    public function template(Ledger $ledger): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');

            if ($output !== false) {
                fputcsv($output, array_values(CsvReader::TEMPLATE), ',', '"', '');
                fclose($output);
            }
        }, 'ledgerfly-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Ledger $ledger, string $import): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($ledger, $import), withSample: true)]);
    }

    public function preview(Request $request, Ledger $ledger, string $import): JsonResponse
    {
        $job = $this->find($ledger, $import);
        $config = $this->config($request, $ledger, $job);
        $account = $ledger->accounts()->with('currency')->findOrFail((string) $config['account_id']);
        $rows = $this->planner->plan($job, $account, $config);

        return response()->json([
            'data' => array_slice($rows, 0, config()->integer('ledgerfly.pagination.max_per_page')),
            'meta' => [
                'total' => count($rows),
                'invalid' => count(array_filter($rows, fn (array $row) => $row['errors'] !== [])),
                'duplicates' => count(array_filter($rows, fn (array $row) => $row['duplicate'])),
            ],
        ]);
    }

    public function run(Request $request, Ledger $ledger, string $import): JsonResponse
    {
        $job = $this->find($ledger, $import);
        abort_unless(in_array($job->status, ['uploaded', 'failed'], true), 409, __('This import already ran.'));

        $config = $this->config($request, $ledger, $job);
        $profileName = $request->validate(['save_profile_as' => ['nullable', 'string', 'max:255']])['save_profile_as'] ?? null;

        if ($profileName) {
            $ledger->importProfiles()->updateOrCreate(['name' => $profileName], [
                'config' => collect($config)->except(['skip_duplicates'])->all(),
            ]);
        }

        $job->update(['config' => $config, 'status' => 'queued', 'error' => null]);
        RunImport::dispatch($job);

        return response()->json(['data' => $this->present($job->refresh())]);
    }

    public function destroy(Ledger $ledger, string $import): Response
    {
        $job = $this->find($ledger, $import);
        Storage::disk(config()->string('ledgerfly.attachments.disk'))->delete($job->path);
        $job->delete();

        return response()->noContent();
    }

    public function destroyProfile(Ledger $ledger, string $profile): Response
    {
        $ledger->importProfiles()->findOrFail($profile)->delete();

        return response()->noContent();
    }

    private function find(Ledger $ledger, string $id): ImportJob
    {
        return $ledger->importJobs()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function config(Request $request, Ledger $ledger, ImportJob $job): array
    {
        $csv = $job->format === 'csv';

        $validated = $request->validate([
            'account_id' => ['required', 'ulid', Rule::exists('accounts', 'id')
                ->where('ledger_id', $ledger->id)
                ->whereIn('type', ['asset', 'liability'])
                ->whereNull('deleted_at')],
            'columns' => [$csv ? 'required' : 'nullable', 'array'],
            'columns.*' => ['nullable', 'integer', 'min:0'],
            'has_header' => ['sometimes', 'boolean'],
            'date_format' => ['nullable', Rule::in(CsvReader::DATE_FORMATS)],
            'decimal_separator' => ['nullable', Rule::in(['.', ','])],
            'delimiter' => ['nullable', Rule::in([',', ';', "\t", '|'])],
            'negate' => ['sometimes', 'boolean'],
            'skip_duplicates' => ['sometimes', 'boolean'],
            'apply_rules' => ['sometimes', 'boolean'],
        ]);

        if ($csv) {
            $columns = array_intersect_key((array) $validated['columns'], array_flip(CsvReader::FIELDS));
            $validated['columns'] = $columns;

            if (! isset($columns['date']) || (! isset($columns['amount']) && ! isset($columns['debit']) && ! isset($columns['credit']))) {
                throw ValidationException::withMessages(['columns' => __('Choose at least the date and amount columns.')]);
            }
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ImportJob $job, bool $withSample = false): array
    {
        $data = [
            'id' => $job->id,
            'filename' => $job->filename,
            'format' => $job->format,
            'status' => $job->status,
            'config' => $job->config,
            'stats' => $job->stats,
            'error' => $job->getAttribute('error'),
            'created_at' => $job->created_at,
            'completed_at' => $job->completed_at,
        ];

        if ($withSample && $job->format === 'csv') {
            $rows = $this->csv->rows((string) Storage::disk(config()->string('ledgerfly.attachments.disk'))->get($job->path));
            $data['sample'] = array_slice($rows, 0, 8);
            $data['suggested_columns'] = $this->suggestColumns($rows[0] ?? []);
        }

        return $data;
    }

    /**
     * Guesses which column holds which field from the header names.
     *
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function suggestColumns(array $header): array
    {
        $suggested = [];

        foreach (self::HEADER_HINTS as $field => $hints) {
            foreach ($header as $index => $name) {
                $name = Str::lower(trim($name));

                if (! in_array($index, $suggested, true) && collect($hints)->contains(fn (string $hint) => $name === $hint || str_contains($name, $hint) && strlen($hint) > 3)) {
                    $suggested[$field] = $index;

                    break;
                }
            }
        }

        return $suggested;
    }
}
