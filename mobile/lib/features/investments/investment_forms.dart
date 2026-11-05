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
    for (final controller in [_name, _symbol, _rate, _amount, _units]) {
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

  @override
  Widget build(BuildContext context) {
    final editing = widget.investment != null;
    final interest = earnsInterest(_kind);
    final units = tracksUnits(_kind);
    final currency = ref.watch(currentLedgerProvider).currency;
    final accounts = _cashAccounts(ref, currency.id);
    final ready = _name.text.trim().isNotEmpty && (!units || _amount.text.trim().isEmpty || _units.text.trim().isNotEmpty) && !_saving;

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
                  child: _DateField(
                    label: 'Start date',
                    value: _start,
                    hint: 'First payment',
                    onChanged: (date) => setState(() => _start = date),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _DateField(
                    label: 'Maturity',
                    value: _maturity,
                    hint: 'Optional',
                    onChanged: (date) => setState(() => _maturity = date),
                  ),
                ),
              ],
            ),
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
            _DateField(label: 'Date', value: _date, onChanged: (date) => setState(() => _date = date ?? _date)),
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
  bool _close = false;
  bool _saving = false;

  bool get _pricePerUnit => widget.type == 'valuation' && widget.investment.tracksUnits;

  bool get _needsUnits => widget.investment.tracksUnits && (widget.type == 'buy' || widget.type == 'sell');

  bool get _movesMoney => widget.type != 'valuation';

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
          _DateField(label: 'Date', value: _date, onChanged: (date) => setState(() => _date = date ?? _date)),
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

class _DateField extends StatelessWidget {
  const _DateField({required this.label, required this.value, required this.onChanged, this.hint});

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
