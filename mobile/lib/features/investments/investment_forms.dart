import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import 'investment_labels.dart';

Future<void> showInvestmentSheet(BuildContext context, WidgetRef ref, {Investment? investment}) async {
  final saved = await showAppSheet<bool>(
    context,
    title: investment == null ? 'New investment' : 'Edit ${investment.name}',
    builder: (_) => _InvestmentForm(investment: investment),
  );
  if (saved == true && context.mounted) showToast(context, investment == null ? 'Investment added' : 'Changes saved');
}

Future<void> showEventSheet(BuildContext context, Investment investment, String type, {DateTime? date, String? amount}) async {
  final saved = await showAppSheet<bool>(
    context,
    title: '${eventLabels(investment.kind)[type]}: ${investment.name}',
    builder: (_) => _EventForm(investment: investment, type: type, date: date, amount: amount),
  );
  if (saved == true && context.mounted) showToast(context, 'Recorded');
}

/// Cash accounts in the given currency, leaving out the accounts investments keep their money in.
List<Account> _cashAccounts(WidgetRef ref, String currencyId) => (ref.watch(accountsProvider('asset')).value ?? const <Account>[])
    .where((account) => account.active && account.role != 'investment' && account.currency?.id == currencyId)
    .toList();

class _InvestmentForm extends ConsumerStatefulWidget {
  const _InvestmentForm({this.investment});

  final Investment? investment;

  @override
  ConsumerState<_InvestmentForm> createState() => _InvestmentFormState();
}

class _InvestmentFormState extends ConsumerState<_InvestmentForm> {
  late final _name = TextEditingController(text: widget.investment?.name);
  late final _symbol = TextEditingController(text: widget.investment?.symbol);
  late final _rate = TextEditingController(text: widget.investment?.interestRate?.toString());
  late final _tax = TextEditingController(text: widget.investment?.taxRate?.toString());
  late final _installment = TextEditingController(text: widget.investment?.contribution?.amount);
  late String _installmentFrequency = widget.investment?.contribution?.frequency ?? 'monthly';
  late String? _installmentAccountId = widget.investment?.contribution?.accountId;
  late DateTime? _nextInstallment = widget.investment?.contribution?.nextDate;
  final _amount = TextEditingController();
  final _units = TextEditingController();
  late String _kind = widget.investment?.kind ?? 'stock';
  late String? _frequency = widget.investment?.payoutFrequency;
  late DateTime? _start = widget.investment?.startDate;
  late DateTime? _maturity = widget.investment?.maturityDate;
  DateTime _date = Dates.today();
  String? _accountId;
  bool _saving = false;

  @override
  void dispose() {
    for (final controller in [_name, _symbol, _rate, _tax, _installment, _amount, _units]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final ledger = ref.read(currentLedgerProvider);
    final interest = earnsInterest(_kind);
    final units = tracksUnits(_kind);
    final body = {
      'kind': _kind,
      'name': _name.text.trim(),
      'symbol': units && _symbol.text.trim().isNotEmpty ? _symbol.text.trim() : null,
      'interest_rate': interest && _rate.text.trim().isNotEmpty ? _rate.text.trim() : null,
      'payout_frequency': interest ? _frequency : null,
      'start_date': isoDate(_start ?? _date),
      'maturity_date': interest && _maturity != null ? isoDate(_maturity!) : null,
      'tax_rate': interest && _tax.text.trim().isNotEmpty ? _tax.text.trim() : null,
      ..._installmentBody(),
    };
    setState(() => _saving = true);
    try {
      final api = ref.read(apiProvider);
      if (widget.investment == null) {
        await api.post(ledgerPath(ledger, 'investments'), {
          ...body,
          'currency_id': ledger.currency.id,
          'purchase': _amount.text.trim().isEmpty
              ? null
              : {
                  'amount': _amount.text.trim(),
                  'units': units ? _units.text.trim() : null,
                  'date': isoDate(_date),
                  'account_id': _accountId,
                },
        });
      } else {
        await api.patch(ledgerPath(ledger, 'investments/${widget.investment!.id}'), body);
      }
      refreshLedgerData(ref.invalidate);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  bool get _hasInstallment => takesInstallments(_kind) && (double.tryParse(_installment.text.trim()) ?? 0) > 0;

  Map<String, Object?> _installmentBody() => {
    'contribution_amount': _hasInstallment ? _installment.text.trim() : null,
    'contribution_frequency': _hasInstallment ? _installmentFrequency : null,
    'contribution_account_id': _hasInstallment ? _installmentAccountId : null,
    'next_contribution_date': _hasInstallment && _nextInstallment != null ? isoDate(_nextInstallment!) : null,
  };

  @override
  Widget build(BuildContext context) {
    final editing = widget.investment != null;
    final interest = earnsInterest(_kind);
    final units = tracksUnits(_kind);
    final currency = widget.investment?.currency ?? ref.watch(currentLedgerProvider).currency;
    final accounts = _cashAccounts(ref, currency.id);
    final ready =
        _name.text.trim().isNotEmpty &&
        (!units || _amount.text.trim().isEmpty || _units.text.trim().isNotEmpty) &&
        (!_hasInstallment || (_installmentAccountId != null && _nextInstallment != null)) &&
        !_saving;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const FieldLabel('Type', required: true),
          QuickPicks(
            items: [
              for (final entry in investmentKinds.entries) (label: entry.value, hint: null, active: entry.key == _kind, leading: null),
            ],
            onPick: (index) => setState(() => _kind = investmentKinds.keys.elementAt(index)),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Name', required: true),
          TextField(
            controller: _name,
            textCapitalization: TextCapitalization.words,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(hintText: interest ? 'City Bank FDR, local business...' : 'Square Pharma, gold coins...'),
          ),
          if (units) ...[
            const SizedBox(height: 16),
            const FieldLabel('Symbol'),
            TextField(
              controller: _symbol,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(hintText: 'Optional'),
            ),
          ],
          if (interest) ...[
            const SizedBox(height: 16),
            const FieldLabel('Yearly return'),
            TextField(
              controller: _rate,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(hintText: '10', suffixText: '%'),
            ),
            const SizedBox(height: 16),
            const FieldLabel('Tax on profit'),
            TextField(
              controller: _tax,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(hintText: 'Optional, taken from each payout', suffixText: '%'),
            ),
            const SizedBox(height: 16),
            const FieldLabel('Pays out'),
            QuickPicks(
              items: [
                (label: 'At maturity', hint: null, active: _frequency == null, leading: null),
                for (final entry in payoutFrequencies.entries)
                  (label: entry.value, hint: null, active: entry.key == _frequency, leading: null),
              ],
              onPick: (index) => setState(() => _frequency = index == 0 ? null : payoutFrequencies.keys.elementAt(index - 1)),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: DateField(
                    label: 'Start date',
                    value: _start,
                    hint: 'First payment',
                    onChanged: (date) => setState(() => _start = date),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DateField(
                    label: 'Maturity',
                    value: _maturity,
                    hint: 'Optional',
                    onChanged: (date) => setState(() => _maturity = date),
                  ),
                ),
              ],
            ),
          ],
          if (takesInstallments(_kind)) ...[
            const SizedBox(height: 20),
            Text('Installment', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 4),
            Text(
              'Money paid in on a schedule. Each one is recorded for you on its date.',
              style: TextStyle(color: Palette.of(context).muted, fontSize: 12.5),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _installment,
              onChanged: (_) => setState(() {}),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(hintText: 'Amount, optional', suffixText: currency.code),
            ),
            if (_hasInstallment) ...[
              const SizedBox(height: 12),
              const FieldLabel('Every', required: true),
              QuickPicks(
                items: [
                  for (final entry in contributionFrequencies.entries)
                    (label: entry.value, hint: null, active: entry.key == _installmentFrequency, leading: null),
                ],
                onPick: (index) => setState(() => _installmentFrequency = contributionFrequencies.keys.elementAt(index)),
              ),
              const SizedBox(height: 12),
              const FieldLabel('Paid from', required: true),
              QuickPicks(
                items: [
                  for (final account in accounts)
                    (label: account.name, hint: null, active: account.id == _installmentAccountId, leading: null),
                ],
                onPick: (index) => setState(() => _installmentAccountId = accounts[index].id),
              ),
              const SizedBox(height: 12),
              DateField(
                label: editing ? 'Next installment' : 'First installment',
                value: _nextInstallment,
                hint: 'Choose a date',
                onChanged: (date) => setState(() => _nextInstallment = date),
              ),
            ],
          ],
          if (!editing) ...[
            const SizedBox(height: 20),
            Text(interest ? 'Amount put in' : 'First purchase', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _amount,
                    onChanged: (_) => setState(() {}),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(hintText: 'Amount', suffixText: currency.code),
                  ),
                ),
                if (units) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _units,
                      onChanged: (_) => setState(() {}),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(hintText: _kind == 'gold' ? 'Quantity' : 'Units'),
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 12),
            DateField(label: 'Date', value: _date, onChanged: (date) => setState(() => _date = date ?? _date)),
            const SizedBox(height: 12),
            const FieldLabel('Paid from'),
            QuickPicks(
              items: [
                (label: 'Already owned', hint: null, active: _accountId == null, leading: null),
                for (final account in accounts) (label: account.name, hint: null, active: account.id == _accountId, leading: null),
              ],
              onPick: (index) => setState(() => _accountId = index == 0 ? null : accounts[index - 1].id),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton(onPressed: ready ? _save : null, child: Text(_saving ? 'Saving...' : (editing ? 'Save changes' : 'Add investment'))),
        ],
      ),
    );
  }
}

class _EventForm extends ConsumerStatefulWidget {
  const _EventForm({required this.investment, required this.type, this.date, this.amount});

  final Investment investment;
  final String type;
  final DateTime? date;
  final String? amount;

  @override
  ConsumerState<_EventForm> createState() => _EventFormState();
}

class _EventFormState extends ConsumerState<_EventForm> {
  late final _amount = TextEditingController(text: widget.amount);
  final _units = TextEditingController();
  late DateTime _date = widget.date ?? Dates.today();
  String? _accountId;
  String? _userId;
  bool _close = false;
  bool _reinvest = false;
  bool _saving = false;

  bool get _pricePerUnit => widget.type == 'valuation' && widget.investment.tracksUnits;

  bool get _needsUnits => widget.investment.tracksUnits && (widget.type == 'buy' || widget.type == 'sell');

  bool get _movesMoney => widget.type != 'valuation' && !_reinvest;

  @override
  void dispose() {
    _amount.dispose();
    _units.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final ledger = ref.read(currentLedgerProvider);
    setState(() => _saving = true);
    try {
      await ref.read(apiProvider).post(ledgerPath(ledger, 'investments/${widget.investment.id}/events'), {
        'type': widget.type,
        'date': isoDate(_date),
        'amount': _pricePerUnit ? null : _amount.text.trim(),
        'price': _pricePerUnit ? _amount.text.trim() : null,
        'units': _needsUnits ? _units.text.trim() : null,
        'account_id': _movesMoney ? _accountId : null,
        'close': _close,
        if (widget.type == 'income') 'reinvest': _reinvest,
        if (widget.type == 'buy' && _userId != null) 'user_id': _userId,
      });
      refreshLedgerData(ref.invalidate);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final investment = widget.investment;
    final accounts = _cashAccounts(ref, investment.currency.id);
    final buying = widget.type == 'buy';
    final members = buying ? ref.watch(membersProvider).value ?? const <Member>[] : const <Member>[];
    final me = ref.watch(userProvider).value?.id;
    if (!buying && _movesMoney) _accountId ??= accounts.firstOrNull?.id;
    final amountLabel = switch (widget.type) {
      'buy' => 'Total paid',
      'sell' => 'Total received',
      'income' => 'Amount received',
      _ => _pricePerUnit ? 'Price per unit' : 'Current value',
    };
    final ready =
        (double.tryParse(_amount.text) ?? 0) > 0 &&
        (!_needsUnits || (double.tryParse(_units.text) ?? 0) > 0) &&
        (buying || !_movesMoney || _accountId != null) &&
        !_saving;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DateField(label: 'Date', value: _date, onChanged: (date) => setState(() => _date = date ?? _date)),
          if (_needsUnits) ...[
            const SizedBox(height: 16),
            FieldLabel(investment.kind == 'gold' ? 'Quantity' : 'Units', required: true),
            TextField(
              controller: _units,
              autofocus: true,
              onChanged: (_) => setState(() {}),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(hintText: widget.type == 'sell' ? 'You hold ${investment.units}' : '0'),
            ),
          ],
          const SizedBox(height: 16),
          FieldLabel(amountLabel, required: true),
          TextField(
            controller: _amount,
            autofocus: !_needsUnits,
            onChanged: (_) => setState(() {}),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(hintText: '0.00', suffixText: investment.currency.code),
          ),
          if (widget.type == 'income') ...[
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: _reinvest,
              onChanged: (value) => setState(() => _reinvest = value),
              title: const Text('Keep it invested'),
              subtitle: Text('The income is added to the investment instead of paid out.', style: TextStyle(color: palette.muted)),
            ),
          ],
          if (members.length > 1) ...[
            const SizedBox(height: 16),
            const FieldLabel('Put in by'),
            QuickPicks(
              items: [for (final member in members) (label: member.name, hint: null, active: member.id == (_userId ?? me), leading: null)],
              onPick: (index) => setState(() => _userId = members[index].id),
            ),
          ],
          if (_movesMoney) ...[
            const SizedBox(height: 16),
            FieldLabel(buying ? 'Paid from' : 'Received in', required: !buying),
            QuickPicks(
              items: [
                if (buying) (label: 'Already owned', hint: null, active: _accountId == null, leading: null),
                for (final account in accounts) (label: account.name, hint: null, active: account.id == _accountId, leading: null),
              ],
              onPick: (index) => setState(() => _accountId = buying ? (index == 0 ? null : accounts[index - 1].id) : accounts[index].id),
            ),
          ],
          if (widget.type == 'sell' && !investment.tracksUnits) ...[
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: _close,
              onChanged: (value) => setState(() => _close = value),
              title: const Text('This closes the investment'),
              subtitle: Text('Everything left is taken out.', style: TextStyle(color: palette.muted)),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton(onPressed: ready ? _save : null, child: Text(_saving ? 'Saving...' : 'Save')),
        ],
      ),
    );
  }
}

Future<void> showRenewSheet(BuildContext context, Investment investment) async {
  final saved = await showAppSheet<bool>(
    context,
    title: 'Renew ${investment.name}',
    builder: (_) => _RenewForm(investment: investment),
  );
  if (saved == true && context.mounted) showToast(context, 'Renewed');
}

class _RenewForm extends ConsumerStatefulWidget {
  const _RenewForm({required this.investment});

  final Investment investment;

  @override
  ConsumerState<_RenewForm> createState() => _RenewFormState();
}

class _RenewFormState extends ConsumerState<_RenewForm> {
  final _amount = TextEditingController();
  final _rate = TextEditingController();
  late DateTime _date = () {
    final maturity = widget.investment.maturityDate;
    return maturity != null && !maturity.isAfter(Dates.today()) ? maturity : Dates.today();
  }();
  DateTime? _maturity;
  bool _saving = false;

  @override
  void dispose() {
    _amount.dispose();
    _rate.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(apiProvider).post(ledgerPath(ref.read(currentLedgerProvider), 'investments/${widget.investment.id}/renew'), {
        'date': isoDate(_date),
        'amount': _amount.text.trim().isEmpty ? null : _amount.text.trim(),
        'maturity_date': _maturity == null ? null : isoDate(_maturity!),
        'interest_rate': _rate.text.trim().isEmpty ? null : _rate.text.trim(),
      });
      refreshLedgerData(ref.invalidate);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final investment = widget.investment;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Starts a new term from this date, keeping the interest earned so far invested.',
            style: TextStyle(color: Palette.of(context).muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: DateField(label: 'Renew on', value: _date, onChanged: (date) => setState(() => _date = date ?? _date)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DateField(
                  label: 'New maturity',
                  value: _maturity,
                  hint: 'Optional',
                  onChanged: (date) => setState(() => _maturity = date),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const FieldLabel('New yearly return'),
          TextField(
            controller: _rate,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              hintText: investment.interestRate == null ? 'Optional' : 'Keep ${rateText(investment.interestRate!)}',
              suffixText: '%',
            ),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Amount'),
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              hintText: 'Current value, ${formatMoney(investment.value, investment.currency.code)}',
              suffixText: investment.currency.code,
            ),
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving...' : 'Renew')),
        ],
      ),
    );
  }
}

Future<void> showTargetsSheet(BuildContext context, List<InvestmentTarget> targets) async {
  final saved = await showAppSheet<bool>(
    context,
    title: 'Target mix',
    builder: (_) => _TargetsForm(targets: targets),
  );
  if (saved == true && context.mounted) showToast(context, 'Targets saved');
}

class _TargetsForm extends ConsumerStatefulWidget {
  const _TargetsForm({required this.targets});

  final List<InvestmentTarget> targets;

  @override
  ConsumerState<_TargetsForm> createState() => _TargetsFormState();
}

class _TargetsFormState extends ConsumerState<_TargetsForm> {
  late final _fields = {
    for (final kind in investmentKinds.keys)
      kind: TextEditingController(
        text: switch (widget.targets.where((target) => target.kind == kind).firstOrNull) {
          final target? => target.percent.toStringAsFixed(2).replaceFirst(RegExp(r'\.?0+$'), ''),
          null => '',
        },
      ),
  };
  bool _saving = false;

  double get _sum => _fields.values.fold(0, (sum, field) => sum + (double.tryParse(field.text.trim()) ?? 0));

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(apiProvider).put(ledgerPath(ref.read(currentLedgerProvider), 'investment-targets'), {
        'targets': [
          for (final MapEntry(key: kind, value: field) in _fields.entries)
            if ((double.tryParse(field.text.trim()) ?? 0) > 0) {'kind': kind, 'percent': field.text.trim()},
        ],
      });
      ref.invalidate(portfolioProvider);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final sum = _sum;
    final valid = sum <= 100 && _fields.values.every((field) => field.text.trim().isEmpty || double.tryParse(field.text.trim()) != null);

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'The share of the portfolio you aim for in each kind. Leave a kind empty for no target.',
            style: TextStyle(color: palette.muted, fontSize: 13),
          ),
          const SizedBox(height: 12),
          for (final MapEntry(key: kind, value: field) in _fields.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                children: [
                  Expanded(child: Text(investmentKinds[kind]!)),
                  SizedBox(
                    width: 110,
                    child: TextField(
                      controller: field,
                      textAlign: TextAlign.right,
                      onChanged: (_) => setState(() {}),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(hintText: '0', suffixText: '%'),
                    ),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 4),
          Text(
            sum > 100 ? 'The targets add up to ${rateText(sum)}, more than 100%.' : 'Total ${rateText(sum)}',
            style: TextStyle(color: sum > 100 ? palette.negative : palette.muted, fontSize: 13),
          ),
          const SizedBox(height: 20),
          FilledButton(onPressed: valid && !_saving ? _save : null, child: Text(_saving ? 'Saving...' : 'Save targets')),
        ],
      ),
    );
  }
}

class DateField extends StatelessWidget {
  const DateField({super.key, required this.label, required this.value, required this.onChanged, this.hint});

  final String label;
  final DateTime? value;
  final String? hint;
  final ValueChanged<DateTime?> onChanged;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FieldLabel(label),
        Material(
          color: palette.surface,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: value ?? Dates.today(),
                firstDate: DateTime(1970),
                lastDate: DateTime(2100),
              );
              if (picked != null) onChanged(picked);
            },
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Icon(LucideIcons.calendar, size: 18, color: palette.subtle),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      value == null ? (hint ?? 'Optional') : formatDate(value!),
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: value == null ? palette.subtle : palette.foreground),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
