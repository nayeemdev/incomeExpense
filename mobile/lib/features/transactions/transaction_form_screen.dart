import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../accounts/accounts_screen.dart';
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
  String? sourceId;
  String? destinationId;
  String? budgetId;

  void dispose() {
    for (final controller in [amount, description, counterparty, category, tags, notes]) {
      controller.dispose();
    }
  }
}

class TransactionFormScreen extends ConsumerStatefulWidget {
  const TransactionFormScreen({super.key, this.groupId});

  final String? groupId;

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
  bool _showMore = false;

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
      final account = ref.read(currentLedgerProvider).defaultAccountId;
      _splits.addAll([_SplitDraft()..sourceId = account, _SplitDraft()..sourceId = account]);
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
    _showMore = first.tags.isNotEmpty || first.notes != null || first.budget != null;
  }

  bool _ownSide(String side) => _type == 'transfer' || (_type == 'withdrawal') == (side == 'source');

  void _applyPick(Transaction pick) {
    final split = _splits.first;
    setState(() {
      split.amount.text = pick.amount;
      split.description.text = pick.description;
      split.category.text = pick.category?.name ?? '';
      split.budgetId = pick.budget?.id;
      split.tags.text = pick.tags.join(', ');
      split.sourceId = pick.source.id;
      split.destinationId = pick.destination.id;
      split.counterparty.text = switch (_type) {
        'withdrawal' => pick.destination.name,
        'deposit' => pick.source.name,
        _ => '',
      };
    });
  }

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
    if (!_form.currentState!.validate()) {
      HapticFeedback.mediumImpact();
      return;
    }
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
      HapticFeedback.lightImpact();
      refreshLedgerData(ref.invalidate);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_editing ? 'Transaction updated' : 'Transaction saved')));
        closeScreen(context);
      }
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

  void _addSplit() => setState(
    () => _splits.add(
      _SplitDraft()
        ..sourceId = _splits.last.sourceId
        ..destinationId = _splits.last.destinationId,
    ),
  );

  @override
  Widget build(BuildContext context) {
    final AsyncValue<TransactionGroup?> group = widget.groupId == null
        ? const AsyncData(null)
        : ref.watch(transactionGroupProvider(widget.groupId!));
    final own = ref.watch(accountsProvider('asset,liability'));

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(icon: const Icon(LucideIcons.x, size: 20), tooltip: 'Close', onPressed: () => closeScreen(context)),
        title: Text(_editing ? 'Edit transaction' : 'Split transaction'),
      ),
      body: AsyncView(
        value: own,
        loading: const _FormSkeleton(),
        builder: (accounts) => AsyncView(
          value: group,
          loading: const _FormSkeleton(),
          builder: (group) {
            _load(group);
            return _buildForm(accounts.where((account) => account.active).toList());
          },
        ),
      ),
    );
  }

  Widget _buildForm(List<Account> ownAccounts) {
    final palette = Palette.of(context);
    final single = _splits.length == 1;
    final picks = !_editing && single
        ? ref.watch(frequentProvider(_type)).value?.transactions ?? const <Transaction>[]
        : const <Transaction>[];
    final currency = ref.watch(currentLedgerProvider).currency;

    return Form(
      key: _form,
      child: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              children: [
                Segmented<String>(options: _typeLabels, value: _type, onChanged: (type) => setState(() => _type = type)),
                if (picks.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  QuickPicks(
                    items: [
                      for (final pick in picks)
                        (label: pick.description, hint: formatMoney(pick.amount, pick.currency.code), active: false, leading: null),
                    ],
                    onPick: (index) => _applyPick(picks[index]),
                  ),
                ],
                const SizedBox(height: 16),
                if (single)
                  _SplitFields(
                    key: ObjectKey(_splits.first),
                    draft: _splits.first,
                    type: _type,
                    ownAccounts: ownAccounts,
                    currency: currency.code,
                    date: _date,
                    onPickDate: _pickDate,
                    showMore: _showMore,
                    onChanged: () => setState(() {}),
                  )
                else ...[
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _title,
                          decoration: const InputDecoration(hintText: 'Title, e.g. Weekly shop'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      _DateField(date: _date, onTap: _pickDate),
                    ],
                  ),
                  for (final (index, split) in _splits.indexed) ...[
                    const SizedBox(height: 12),
                    SurfaceCard(
                      padding: const EdgeInsets.fromLTRB(14, 4, 4, 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('Split ${index + 1}', style: Theme.of(context).textTheme.titleSmall)),
                              IconButton(
                                tooltip: 'Remove split',
                                icon: Icon(LucideIcons.trash2, size: 18, color: palette.muted),
                                onPressed: () => setState(() {
                                  _splits.remove(split);
                                  split.dispose();
                                }),
                              ),
                            ],
                          ),
                          Padding(
                            padding: const EdgeInsets.only(right: 10),
                            child: _SplitFields(
                              key: ObjectKey(split),
                              draft: split,
                              type: _type,
                              ownAccounts: ownAccounts,
                              currency: currency.code,
                              showMore: true,
                              onChanged: () => setState(() {}),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
                const SizedBox(height: 6),
                Row(
                  children: [
                    if (single)
                      TextButton.icon(
                        onPressed: () => setState(() => _showMore = !_showMore),
                        icon: Icon(_showMore ? LucideIcons.chevronUp : LucideIcons.chevronDown, size: 16),
                        label: Text(_showMore ? 'Fewer details' : 'Budget, tags, notes'),
                      ),
                    const Spacer(),
                    TextButton.icon(onPressed: _addSplit, icon: const Icon(LucideIcons.split, size: 16), label: const Text('Add split')),
                  ],
                ),
              ],
            ),
          ),
          _SaveBar(saving: _saving, onSave: _save, label: _editing ? 'Save changes' : 'Save ${_typeLabels[_type]!.toLowerCase()}'),
        ],
      ),
    );
  }
}

class _SaveBar extends StatelessWidget {
  const _SaveBar({required this.saving, required this.onSave, required this.label});

  final bool saving;
  final VoidCallback onSave;
  final String label;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Container(
      decoration: BoxDecoration(
        color: palette.card,
        border: Border(top: BorderSide(color: palette.border)),
      ),
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
      child: SafeArea(
        top: false,
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(
            onPressed: saving ? () {} : onSave,
            child: saving
                ? SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary),
                  )
                : Text(label),
          ),
        ),
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({required this.date, required this.onTap});

  final DateTime date;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 45,
      child: OutlinedButton.icon(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 12)),
        icon: Icon(LucideIcons.calendar, size: 16, color: Palette.of(context).muted),
        label: Text(formatDay(date)),
      ),
    );
  }
}

class _SplitFields extends ConsumerWidget {
  const _SplitFields({
    super.key,
    required this.draft,
    required this.type,
    required this.ownAccounts,
    required this.currency,
    required this.showMore,
    required this.onChanged,
    this.date,
    this.onPickDate,
  });

  final _SplitDraft draft;
  final String type;
  final List<Account> ownAccounts;
  final String currency;
  final bool showMore;
  final VoidCallback onChanged;
  final DateTime? date;
  final VoidCallback? onPickDate;

  String? _amount(String? value) {
    final amount = double.tryParse(value?.trim() ?? '');
    return amount == null || amount <= 0 ? 'Enter an amount' : null;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final frequent = ref.watch(frequentProvider(type)).value;
    final categoryNames = ref.watch(categoriesProvider).value?.map((category) => category.name).toList() ?? const <String>[];
    final budgets = type == 'withdrawal' ? ref.watch(budgetsProvider).value ?? const <Budget>[] : const <Budget>[];
    final counterpartyType = type == 'deposit' ? 'revenue' : 'expense';
    final counterpartyNames =
        ref.watch(accountsProvider(counterpartyType)).value?.map((account) => account.name).toList() ?? const <String>[];
    final payeeChips = (frequent?.counterparties ?? const <AccountRef>[])
        .where((account) => account.type == counterpartyType)
        .take(8)
        .toList();
    final categoryChips = (frequent?.categories ?? const <NamedRef>[]).take(8).toList();

    Widget own(String label, bool source) => _AccountSelect(
      label: label,
      accounts: ownAccounts,
      value: source ? draft.sourceId : draft.destinationId,
      onChanged: (id) {
        if (source) {
          draft.sourceId = id;
        } else {
          draft.destinationId = id;
        }
        onChanged();
      },
    );

    Widget counterparty(String label, String hint) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FieldLabel(label),
        _Suggest(controller: draft.counterparty, options: counterpartyNames, hint: hint),
      ],
    );

    final from = type == 'deposit' ? counterparty('From', 'Who paid you') : own('From', true);
    final to = type == 'withdrawal' ? counterparty('To', 'Optional') : own('To', false);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const FieldLabel('Amount', required: true),
                  TextFormField(
                    controller: draft.amount,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
                    style: const TextStyle(
                      fontSize: 16,
                      height: 1.3,
                      fontWeight: FontWeight.w600,
                      fontFeatures: [FontFeature.tabularFigures()],
                    ),
                    decoration: InputDecoration(
                      hintText: '0.00',
                      suffixIcon: Padding(
                        padding: const EdgeInsets.only(right: 12),
                        child: Center(
                          widthFactor: 1,
                          child: Text(currency, style: TextStyle(color: palette.muted, fontSize: 13)),
                        ),
                      ),
                    ),
                    validator: _amount,
                  ),
                ],
              ),
            ),
            if (date != null) ...[
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const FieldLabel('Date', required: true),
                  _DateField(date: date!, onTap: onPickDate!),
                ],
              ),
            ],
          ],
        ),
        const SizedBox(height: 14),
        const FieldLabel('Description', required: true),
        TextFormField(
          controller: draft.description,
          textCapitalization: TextCapitalization.sentences,
          textInputAction: TextInputAction.next,
          decoration: const InputDecoration(hintText: 'What was it for?'),
          validator: (value) => (value == null || value.trim().isEmpty) ? 'Add a description' : null,
        ),
        const SizedBox(height: 14),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: from),
            const SizedBox(width: 10),
            Expanded(child: to),
          ],
        ),
        if (type != 'transfer' && payeeChips.isNotEmpty) ...[
          const SizedBox(height: 8),
          QuickPicks(
            items: [
              for (final payee in payeeChips)
                (label: payee.name, hint: null, active: draft.counterparty.text.trim() == payee.name, leading: null),
            ],
            onPick: (index) {
              draft.counterparty.text = payeeChips[index].name;
              onChanged();
            },
          ),
        ],
        if (type != 'transfer') ...[
          const SizedBox(height: 14),
          const FieldLabel('Category'),
          _Suggest(controller: draft.category, options: categoryNames, hint: 'No category'),
          if (categoryChips.isNotEmpty) ...[
            const SizedBox(height: 8),
            QuickPicks(
              items: [
                for (final category in categoryChips)
                  (label: category.name, hint: null, active: draft.category.text.trim() == category.name, leading: null),
              ],
              onPick: (index) {
                final name = categoryChips[index].name;
                draft.category.text = draft.category.text.trim() == name ? '' : name;
                onChanged();
              },
            ),
          ],
        ],
        AnimatedSize(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          alignment: Alignment.topCenter,
          child: showMore
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (budgets.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      const FieldLabel('Budget'),
                      QuickPicks(
                        items: [
                          for (final budget in budgets)
                            (label: budget.name, hint: null, active: draft.budgetId == budget.id, leading: null),
                        ],
                        onPick: (index) {
                          draft.budgetId = draft.budgetId == budgets[index].id ? null : budgets[index].id;
                          onChanged();
                        },
                      ),
                    ],
                    const SizedBox(height: 14),
                    const FieldLabel('Tags'),
                    TextFormField(
                      controller: draft.tags,
                      decoration: const InputDecoration(hintText: 'Separate with commas'),
                    ),
                    const SizedBox(height: 14),
                    const FieldLabel('Notes'),
                    TextFormField(
                      controller: draft.notes,
                      minLines: 2,
                      maxLines: 5,
                      decoration: const InputDecoration(hintText: 'Anything worth remembering'),
                    ),
                  ],
                )
              : const SizedBox(width: double.infinity),
        ),
      ],
    );
  }
}

/// A field that opens a bottom sheet of your own accounts, with balances, to choose from.
class _AccountSelect extends StatelessWidget {
  const _AccountSelect({required this.label, required this.accounts, required this.value, required this.onChanged});

  final String label;
  final List<Account> accounts;
  final String? value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final selected = accounts.where((account) => account.id == value).firstOrNull;

    return FormField<String>(
      key: ValueKey(value),
      initialValue: selected?.id,
      validator: (id) => id == null ? 'Choose one' : null,
      builder: (field) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FieldLabel(label, required: true),
          InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () async {
              final id = await showModalBottomSheet<String>(
                context: context,
                showDragHandle: true,
                isScrollControlled: true,
                builder: (context) => _AccountSheet(title: label, accounts: accounts, selected: value),
              );
              if (id != null) {
                field.didChange(id);
                onChanged(id);
              }
            },
            child: InputDecorator(
              isEmpty: selected == null,
              decoration: InputDecoration(
                hintText: 'Choose',
                errorText: field.errorText,
                suffixIcon: Icon(LucideIcons.chevronsUpDown, size: 16, color: palette.muted),
              ),
              child: selected == null
                  ? null
                  : Text(selected.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodyLarge),
            ),
          ),
        ],
      ),
    );
  }
}

class _AccountSheet extends StatelessWidget {
  const _AccountSheet({required this.title, required this.accounts, required this.selected});

  final String title;
  final List<Account> accounts;
  final String? selected;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.7),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text(title, style: text.titleLarge),
            ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.only(bottom: 12),
                children: [
                  for (final account in accounts)
                    ListTile(
                      onTap: () => Navigator.pop(context, account.id),
                      leading: CategoryIcon(glyph: accountIcon(account), color: account.id == selected ? 'blue' : 'slate', size: 40),
                      title: Text(account.name, style: text.titleSmall),
                      subtitle: roleLabel(account) == null
                          ? null
                          : Text(roleLabel(account)!, style: text.bodySmall?.copyWith(color: palette.muted)),
                      trailing: account.id == selected
                          ? Icon(LucideIcons.check, size: 18, color: Theme.of(context).colorScheme.primary)
                          : account.balance == null || account.currency == null
                          ? null
                          : MoneyText(
                              account.balance!,
                              account.currency!.code,
                              decimals: account.currency!.decimals,
                              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                            ),
                    ),
                ],
              ),
            ),
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
    final palette = Palette.of(context);
    return RawAutocomplete<String>(
      textEditingController: widget.controller,
      focusNode: _focus,
      optionsBuilder: (value) {
        final query = value.text.trim().toLowerCase();
        if (query.isEmpty) return const [];
        return widget.options.where((option) => option.toLowerCase().contains(query) && option.toLowerCase() != query).take(5);
      },
      fieldViewBuilder: (context, controller, focusNode, onSubmitted) => TextFormField(
        controller: controller,
        focusNode: focusNode,
        textCapitalization: TextCapitalization.words,
        textInputAction: TextInputAction.next,
        decoration: InputDecoration(hintText: widget.hint),
      ),
      optionsViewBuilder: (context, onSelected, options) => Align(
        alignment: Alignment.topLeft,
        child: Container(
          margin: const EdgeInsets.only(top: 4),
          constraints: const BoxConstraints(maxHeight: 220, maxWidth: 300),
          decoration: BoxDecoration(
            color: palette.card,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: palette.border),
            boxShadow: const [BoxShadow(color: Color(0x290F2942), blurRadius: 24, offset: Offset(0, 8))],
          ),
          child: Material(
            type: MaterialType.transparency,
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 4),
              shrinkWrap: true,
              children: [
                for (final option in options)
                  InkWell(
                    onTap: () => onSelected(option),
                    child: Padding(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), child: Text(option)),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FormSkeleton extends StatelessWidget {
  const _FormSkeleton();

  @override
  Widget build(BuildContext context) {
    Widget field() => const Padding(
      padding: EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [Skeleton(width: 80, height: 12), SizedBox(height: 8), Skeleton(height: 46, radius: 8)],
      ),
    );
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [const Skeleton(height: 40, radius: 10), const SizedBox(height: 20), field(), field(), field(), field()],
    );
  }
}
