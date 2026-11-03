import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../api/models.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';

const _typeLabels = {'withdrawal': 'Expense', 'deposit': 'Income', 'transfer': 'Transfer'};

class _SplitDraft {
  _SplitDraft({Transaction? from})
      : amount = TextEditingController(text: from?.amount ?? ''),
        description = TextEditingController(text: from?.description ?? ''),
        counterparty = TextEditingController(),
        category = TextEditingController(text: from?.category?.name ?? ''),
        tags = TextEditingController(text: from?.tags.join(', ') ?? ''),
        notes = TextEditingController(text: from?.notes ?? ''),
        budgetId = from?.budget?.id;

  final TextEditingController amount;
  final TextEditingController description;
  final TextEditingController counterparty;
  final TextEditingController category;
  final TextEditingController tags;
  final TextEditingController notes;
  int? sourceId;
  int? destinationId;
  int? budgetId;

  void dispose() {
    for (final controller in [amount, description, counterparty, category, tags, notes]) {
      controller.dispose();
    }
  }
}

class TransactionFormScreen extends ConsumerStatefulWidget {
  const TransactionFormScreen({super.key, this.groupId});

  final int? groupId;

  @override
  ConsumerState<TransactionFormScreen> createState() => _TransactionFormScreenState();
}

class _TransactionFormScreenState extends ConsumerState<TransactionFormScreen> {
  final _form = GlobalKey<FormState>();
  final _title = TextEditingController();
  final List<_SplitDraft> _splits = [];
  String _type = 'withdrawal';
  DateTime _date = Dates.today();
  bool _loaded = false;
  bool _saving = false;

  bool get _editing => widget.groupId != null;

  @override
  void dispose() {
    _title.dispose();
    for (final split in _splits) {
      split.dispose();
    }
    super.dispose();
  }

  void _load(TransactionGroup? group) {
    if (_loaded) return;
    _loaded = true;
    if (group == null) {
      _splits.add(_SplitDraft()..sourceId = ref.read(currentLedgerProvider).defaultAccountId);
      return;
    }
    final first = group.splits.first;
    _type = first.type;
    _date = first.date;
    _title.text = group.title ?? '';
    for (final transaction in group.splits) {
      final draft = _SplitDraft(from: transaction)
        ..sourceId = transaction.source.id
        ..destinationId = transaction.destination.id;
      if (_type == 'withdrawal') draft.counterparty.text = transaction.destination.name;
      if (_type == 'deposit') draft.counterparty.text = transaction.source.name;
      _splits.add(draft);
    }
  }

  bool _ownSide(String side) => _type == 'transfer' || (_type == 'withdrawal') == (side == 'source');

  Json _payload(_SplitDraft split) {
    final description = split.description.text.trim();
    final counterparty = split.counterparty.text.trim().isEmpty ? description : split.counterparty.text.trim();
    final tags = split.tags.text.split(',').map((tag) => tag.trim()).where((tag) => tag.isNotEmpty).toList();
    return {
      'type': _type,
      'date': isoDate(_date),
      'description': description,
      'amount': split.amount.text.trim(),
      if (_ownSide('source')) 'source_id': split.sourceId else 'source_name': counterparty,
      if (_ownSide('destination')) 'destination_id': split.destinationId else 'destination_name': counterparty,
      'category_name': split.category.text.trim().isEmpty ? null : split.category.text.trim(),
      'budget_id': _type == 'withdrawal' ? split.budgetId : null,
      'tags': tags,
      'notes': split.notes.text.trim().isEmpty ? null : split.notes.text.trim(),
    };
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _saving = true);
    final ledger = ref.read(currentLedgerProvider);
    final api = ref.read(apiProvider);
    final body = {
      'title': _splits.length > 1 && _title.text.trim().isNotEmpty ? _title.text.trim() : null,
      'transactions': _splits.map(_payload).toList(),
    };
    try {
      if (_editing) {
        await api.put(ledgerPath(ledger, 'transactions/${widget.groupId}'), body);
      } else {
        await api.post(ledgerPath(ledger, 'transactions'), body);
      }
      refreshLedgerData(ref);
      if (mounted) context.pop();
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime(2000), lastDate: DateTime(2100));
    if (picked != null) setState(() => _date = picked);
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<TransactionGroup?> group =
        widget.groupId == null ? const AsyncData(null) : ref.watch(transactionGroupProvider(widget.groupId!));
    final own = ref.watch(accountsProvider('asset,liability'));

    return Scaffold(
      appBar: AppBar(
        title: Text(_editing ? 'Edit transaction' : 'New transaction'),
        actions: [
          TextButton(onPressed: _saving ? null : _save, child: const Text('Save')),
        ],
      ),
      body: AsyncView(
        value: own,
        builder: (accounts) => AsyncView(
          value: group,
          builder: (group) {
            _load(group);
            return _buildForm(accounts.where((account) => account.active).toList());
          },
        ),
      ),
    );
  }

  Widget _buildForm(List<Account> ownAccounts) {
    return Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          SegmentedButton<String>(
            segments: [for (final MapEntry(:key, :value) in _typeLabels.entries) ButtonSegment(value: key, label: Text(value))],
            selected: {_type},
            onSelectionChanged: _editing ? null : (selection) => setState(() => _type = selection.first),
            showSelectedIcon: false,
          ),
          const SizedBox(height: 16),
          const FieldLabel('Date', required: true),
          OutlinedButton.icon(
            onPressed: _pickDate,
            icon: const Icon(Icons.calendar_today_outlined, size: 18),
            label: Align(alignment: Alignment.centerLeft, child: Text(formatDay(_date))),
          ),
          if (_splits.length > 1) ...[
            const SizedBox(height: 16),
            const FieldLabel('Title'),
            TextFormField(controller: _title, decoration: const InputDecoration(hintText: 'Shown for the whole split transaction')),
          ],
          for (final (index, split) in _splits.indexed) ...[
            const SizedBox(height: 16),
            _SplitCard(
              key: ObjectKey(split),
              draft: split,
              type: _type,
              ownAccounts: ownAccounts,
              title: _splits.length > 1 ? 'Split ${index + 1}' : null,
              onRemove: _splits.length > 1
                  ? () => setState(() {
                        _splits.remove(split);
                        split.dispose();
                      })
                  : null,
            ),
          ],
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => setState(() => _splits.add(_SplitDraft()
              ..sourceId = _splits.last.sourceId
              ..destinationId = _splits.last.destinationId)),
            icon: const Icon(Icons.call_split),
            label: const Text('Add split'),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Save'),
          ),
        ],
      ),
    );
  }
}

class _SplitCard extends ConsumerStatefulWidget {
  const _SplitCard({super.key, required this.draft, required this.type, required this.ownAccounts, this.title, this.onRemove});

  final _SplitDraft draft;
  final String type;
  final List<Account> ownAccounts;
  final String? title;
  final VoidCallback? onRemove;

  @override
  ConsumerState<_SplitCard> createState() => _SplitCardState();
}

class _SplitCardState extends ConsumerState<_SplitCard> {
  _SplitDraft get draft => widget.draft;

  String? _required(String? value) => (value == null || value.trim().isEmpty) ? 'Required' : null;

  String? _amount(String? value) {
    final amount = double.tryParse(value?.trim() ?? '');
    return amount == null || amount <= 0 ? 'Enter an amount above zero' : null;
  }

  Widget _accountField(String label, int? value, ValueChanged<int?> onChanged) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FieldLabel(label, required: true),
        DropdownButtonFormField<int>(
          initialValue: widget.ownAccounts.any((account) => account.id == value) ? value : null,
          isExpanded: true,
          items: [
            for (final account in widget.ownAccounts)
              DropdownMenuItem(value: account.id, child: Text(account.name, overflow: TextOverflow.ellipsis)),
          ],
          onChanged: (id) => setState(() => onChanged(id)),
          validator: (id) => id == null ? 'Choose an account' : null,
        ),
      ],
    );
  }

  Widget _counterpartyField(String label, String hint, String type) {
    final names = ref.watch(accountsProvider(type)).value?.map((account) => account.name).toList() ?? const [];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FieldLabel(label),
        _Suggest(controller: draft.counterparty, options: names, hint: hint),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final type = widget.type;
    final categories = ref.watch(categoriesProvider).value?.map((category) => category.name).toList() ?? const [];
    final budgets = type == 'withdrawal' ? ref.watch(budgetsProvider).value ?? const <Budget>[] : const <Budget>[];

    final from = switch (type) {
      'deposit' => _counterpartyField('From', 'Who paid you (optional)', 'revenue'),
      _ => _accountField('From', draft.sourceId, (id) => draft.sourceId = id),
    };
    final to = switch (type) {
      'withdrawal' => _counterpartyField('To', 'Who you paid (optional)', 'expense'),
      _ => _accountField('To', draft.destinationId, (id) => draft.destinationId = id),
    };

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (widget.title != null)
              Row(
                children: [
                  Expanded(child: Text(widget.title!, style: Theme.of(context).textTheme.titleSmall)),
                  if (widget.onRemove != null)
                    IconButton(onPressed: widget.onRemove, icon: const Icon(Icons.delete_outline), tooltip: 'Remove split'),
                ],
              ),
            const FieldLabel('Amount', required: true),
            TextFormField(
              controller: draft.amount,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: Theme.of(context).textTheme.titleMedium,
              validator: _amount,
            ),
            const SizedBox(height: 12),
            const FieldLabel('Description', required: true),
            TextFormField(
              controller: draft.description,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(hintText: 'What was it for?'),
              validator: _required,
            ),
            const SizedBox(height: 12),
            from,
            const SizedBox(height: 12),
            to,
            const SizedBox(height: 12),
            const FieldLabel('Category'),
            _Suggest(controller: draft.category, options: categories, hint: 'No category'),
            if (budgets.isNotEmpty) ...[
              const SizedBox(height: 12),
              const FieldLabel('Budget'),
              DropdownButtonFormField<int?>(
                initialValue: budgets.any((budget) => budget.id == draft.budgetId) ? draft.budgetId : null,
                isExpanded: true,
                items: [
                  const DropdownMenuItem(child: Text('No budget')),
                  for (final budget in budgets) DropdownMenuItem(value: budget.id, child: Text(budget.name)),
                ],
                onChanged: (id) => draft.budgetId = id,
              ),
            ],
            const SizedBox(height: 12),
            const FieldLabel('Tags'),
            TextFormField(controller: draft.tags, decoration: const InputDecoration(hintText: 'Separate with commas')),
            const SizedBox(height: 12),
            const FieldLabel('Notes'),
            TextFormField(controller: draft.notes, minLines: 2, maxLines: 5),
          ],
        ),
      ),
    );
  }
}

class _Suggest extends StatefulWidget {
  const _Suggest({required this.controller, required this.options, required this.hint});

  final TextEditingController controller;
  final List<String> options;
  final String hint;

  @override
  State<_Suggest> createState() => _SuggestState();
}

class _SuggestState extends State<_Suggest> {
  final _focus = FocusNode();

  @override
  void dispose() {
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RawAutocomplete<String>(
      textEditingController: widget.controller,
      focusNode: _focus,
      optionsBuilder: (value) {
        final query = value.text.trim().toLowerCase();
        if (query.isEmpty) return const [];
        return widget.options.where((option) => option.toLowerCase().contains(query)).take(6);
      },
      fieldViewBuilder: (context, controller, focusNode, onSubmitted) => TextFormField(
        controller: controller,
        focusNode: focusNode,
        textCapitalization: TextCapitalization.words,
        decoration: InputDecoration(hintText: widget.hint),
      ),
      optionsViewBuilder: (context, onSelected, options) => Align(
        alignment: Alignment.topLeft,
        child: Material(
          elevation: 4,
          borderRadius: BorderRadius.circular(10),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 240, maxWidth: 360),
            child: ListView(
              padding: EdgeInsets.zero,
              shrinkWrap: true,
              children: [for (final option in options) ListTile(dense: true, title: Text(option), onTap: () => onSelected(option))],
            ),
          ),
        ),
      ),
    );
  }
}
