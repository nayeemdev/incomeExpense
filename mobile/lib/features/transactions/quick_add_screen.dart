import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../accounts/accounts_screen.dart';
import '../auth/session.dart';
import '../categories/categories.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';

const _types = {'withdrawal': 'Expense', 'deposit': 'Income', 'transfer': 'Transfer'};

/// Adds or edits a single transaction with an on-screen keypad, so nothing needs the system keyboard but the title.
class QuickAddScreen extends ConsumerStatefulWidget {
  const QuickAddScreen({super.key, this.type = 'withdrawal', this.group, this.amount, this.title});

  final String type;
  final TransactionGroup? group;

  /// Prefills from a widget or Siri shortcut link.
  final String? amount;
  final String? title;

  @override
  ConsumerState<QuickAddScreen> createState() => _QuickAddScreenState();
}

class _QuickAddScreenState extends ConsumerState<QuickAddScreen> {
  final _title = TextEditingController();
  late String _type = widget.type;
  String _amount = '';
  NamedRef? _category;
  int? _fromId;
  int? _toId;
  String _payee = '';
  DateTime _date = Dates.today();
  int? _budgetId;
  String _tags = '';
  String _notes = '';
  bool _saving = false;

  Transaction? get _editing => widget.group?.splits.first;

  @override
  void initState() {
    super.initState();
    final existing = _editing;
    if (existing == null) {
      _fromId = ref.read(currentLedgerProvider).defaultAccountId;
      _toId = _type == 'deposit' ? _fromId : null;
      if ((double.tryParse(widget.amount ?? '') ?? 0) > 0) _amount = widget.amount!;
      _title.text = widget.title ?? '';
      return;
    }
    _type = existing.type;
    _amount = existing.amount;
    _title.text = existing.description;
    _category = existing.category;
    _fromId = existing.source.id;
    _toId = existing.destination.id;
    _payee = switch (_type) {
      'withdrawal' => existing.destination.name,
      'deposit' => existing.source.name,
      _ => '',
    };
    _date = existing.date;
    _budgetId = existing.budget?.id;
    _tags = existing.tags.join(', ');
    _notes = existing.notes ?? '';
  }

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  int? get _ownAccountId => _type == 'deposit' ? _toId : _fromId;

  void _setOwnAccount(int id) => setState(() => _type == 'deposit' ? _toId = id : _fromId = id);

  void _applyPick(Transaction pick) {
    setState(() {
      _amount = pick.amount;
      _title.text = pick.description;
      _category = pick.category;
      _fromId = pick.source.id;
      _toId = pick.destination.id;
      _payee = _type == 'withdrawal' ? pick.destination.name : (_type == 'deposit' ? pick.source.name : '');
      _budgetId = pick.budget?.id;
      _tags = pick.tags.join(', ');
    });
  }

  String? get _problem {
    if ((double.tryParse(_amount) ?? 0) <= 0) return 'Enter an amount';
    if (_title.text.trim().isEmpty) return 'Add a title';
    if (_type == 'transfer' && (_fromId == null || _toId == null)) return 'Choose both accounts';
    if (_ownAccountId == null && _type != 'transfer') return 'Choose an account';
    return null;
  }

  Future<void> _save() async {
    final problem = _problem;
    if (problem != null) {
      HapticFeedback.mediumImpact();
      showToast(context, problem);
      return;
    }
    final description = _title.text.trim();
    final counterparty = _payee.trim().isEmpty ? description : _payee.trim();
    final split = {
      'type': _type,
      'date': isoDate(_date),
      'description': description,
      'amount': _amount,
      if (_type == 'deposit') 'source_name': counterparty else 'source_id': _fromId,
      if (_type == 'withdrawal') 'destination_name': counterparty else 'destination_id': _toId,
      'category_name': _type == 'transfer' ? null : _category?.name,
      'budget_id': _type == 'withdrawal' ? _budgetId : null,
      'tags': _tags.split(',').map((tag) => tag.trim()).where((tag) => tag.isNotEmpty).toList(),
      'notes': _notes.trim().isEmpty ? null : _notes.trim(),
    };
    final ledger = ref.read(currentLedgerProvider);
    final api = ref.read(apiProvider);
    setState(() => _saving = true);
    try {
      final body = {
        'transactions': [split],
      };
      if (widget.group != null) {
        await api.put(ledgerPath(ledger, 'transactions/${widget.group!.id}'), body);
      } else {
        await api.post(ledgerPath(ledger, 'transactions'), body);
      }
      HapticFeedback.mediumImpact();
      refreshLedgerData(ref);
      if (mounted) {
        showToast(context, widget.group == null ? '${_types[_type]} saved' : 'Transaction updated');
        context.pop();
      }
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final ledger = ref.watch(currentLedgerProvider);
    final accounts = ref.watch(accountsProvider('asset,liability')).value?.where((account) => account.active).toList() ?? const <Account>[];
    final picks = widget.group == null
        ? ref.watch(frequentProvider(_type)).value?.transactions ?? const <Transaction>[]
        : const <Transaction>[];
    final typeColor = switch (_type) {
      'withdrawal' => palette.negative,
      'deposit' => palette.positive,
      _ => palette.primary,
    };
    Account? find(int? id) => accounts.where((account) => account.id == id).firstOrNull;

    return Scaffold(
      resizeToAvoidBottomInset: false,
      appBar: AppBar(
        leading: IconButton(icon: const Icon(LucideIcons.x, size: 22), tooltip: 'Close', onPressed: () => context.pop()),
        title: Text(widget.group == null ? 'New transaction' : 'Edit transaction'),
        actions: [
          if (widget.group == null)
            IconButton(
              tooltip: 'Split into parts',
              icon: const Icon(LucideIcons.split, size: 20),
              onPressed: () => context.pushReplacement('/new/split'),
            ),
          const SizedBox(width: 4),
        ],
      ),
      body: SafeArea(
        child: GestureDetector(
          behavior: HitTestBehavior.translucent,
          onTap: () => FocusScope.of(context).unfocus(),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Segmented<String>(
                  options: _types,
                  value: _type,
                  colors: {'withdrawal': palette.negative, 'deposit': palette.positive, 'transfer': palette.primary},
                  onChanged: (type) => setState(() {
                    final own = _ownAccountId;
                    _type = type;
                    if (type == 'deposit') _toId = own;
                    if (type == 'withdrawal') _fromId = own;
                  }),
                ),
              ),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: AnimatedSwitcher(
                        duration: const Duration(milliseconds: 120),
                        child: BigAmount(
                          _amount.isEmpty ? '0' : _amount,
                          ledger.currency.code,
                          key: ValueKey(_amount),
                          size: 50,
                          color: _amount.isEmpty ? palette.subtle : typeColor,
                          decimals: ledger.currency.decimals,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 40),
                      child: TextField(
                        controller: _title,
                        textAlign: TextAlign.center,
                        textCapitalization: TextCapitalization.sentences,
                        textInputAction: TextInputAction.done,
                        onChanged: (_) => setState(() {}),
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500),
                        decoration: const InputDecoration(hintText: 'Add title, e.g. Rickshaw'),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: 40,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  children: [
                    if (_type != 'transfer')
                      _Chip(
                        leading: _category == null
                            ? Icon(LucideIcons.layoutGrid, size: 16, color: palette.muted)
                            : CategoryIcon(icon: _category!.icon, color: _category!.color, size: 26),
                        label: _category?.name ?? 'Category',
                        set: _category != null,
                        onTap: () async {
                          final picked = await pickCategory(context, selected: _category);
                          if (picked != null) setState(() => _category = picked);
                        },
                      ),
                    _Chip(
                      leading: Icon(LucideIcons.wallet, size: 16, color: palette.muted),
                      label: _type == 'transfer' ? (find(_fromId)?.name ?? 'From') : (find(_ownAccountId)?.name ?? 'Account'),
                      set: true,
                      onTap: () async {
                        final id = await pickAccount(
                          context,
                          accounts,
                          selected: _type == 'transfer' ? _fromId : _ownAccountId,
                          title: _type == 'deposit' ? 'Paid into' : 'Paid from',
                        );
                        if (id != null) _type == 'transfer' ? setState(() => _fromId = id) : _setOwnAccount(id);
                      },
                    ),
                    if (_type == 'transfer')
                      _Chip(
                        leading: Icon(LucideIcons.arrowRight, size: 16, color: palette.muted),
                        label: find(_toId)?.name ?? 'To',
                        set: _toId != null,
                        onTap: () async {
                          final id = await pickAccount(context, accounts, selected: _toId, title: 'Transfer to');
                          if (id != null) setState(() => _toId = id);
                        },
                      )
                    else
                      _Chip(
                        leading: Icon(_type == 'deposit' ? LucideIcons.briefcase : LucideIcons.store, size: 16, color: palette.muted),
                        label: _payee.isEmpty ? (_type == 'deposit' ? 'Payer' : 'Payee') : _payee,
                        set: _payee.isNotEmpty,
                        onTap: () async {
                          final value = await showAppSheet<String>(
                            context,
                            title: _type == 'deposit' ? 'Who paid you?' : 'Who did you pay?',
                            builder: (_) => _PayeeSheet(type: _type, initial: _payee),
                          );
                          if (value != null) setState(() => _payee = value);
                        },
                      ),
                    _Chip(
                      leading: Icon(LucideIcons.calendar, size: 16, color: palette.muted),
                      label: formatDay(_date),
                      set: true,
                      onTap: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _date,
                          firstDate: DateTime(2000),
                          lastDate: DateTime(2100),
                        );
                        if (picked != null) setState(() => _date = picked);
                      },
                    ),
                    _Chip(
                      leading: Icon(LucideIcons.slidersHorizontal, size: 16, color: palette.muted),
                      label: 'Details',
                      set: _budgetId != null || _tags.isNotEmpty || _notes.isNotEmpty,
                      onTap: () async {
                        final result = await showAppSheet<({int? budget, String tags, String notes})>(
                          context,
                          title: 'Details',
                          builder: (_) => _DetailsSheet(type: _type, budget: _budgetId, tags: _tags, notes: _notes),
                        );
                        if (result != null) {
                          setState(() {
                            _budgetId = result.budget;
                            _tags = result.tags;
                            _notes = result.notes;
                          });
                        }
                      },
                    ),
                  ],
                ),
              ),
              if (picks.isNotEmpty) ...[
                const SizedBox(height: 10),
                QuickPicks(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  items: [
                    for (final pick in picks)
                      (
                        label: pick.description,
                        hint: formatMoney(pick.amount, pick.currency.code),
                        active: false,
                        leading: pick.category == null
                            ? null
                            : CategoryIcon(icon: pick.category!.icon, color: pick.category!.color, size: 26),
                      ),
                  ],
                  onPick: (index) => _applyPick(picks[index]),
                ),
              ],
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 8),
                child: AmountKeypad(onKey: (key) => setState(() => _amount = applyKey(_amount, key, decimals: ledger.currency.decimals))),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _saving ? () {} : _save,
                    child: _saving
                        ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
                        : Text(widget.group == null ? 'Save ${_types[_type]!.toLowerCase()}' : 'Save changes'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.leading, required this.label, required this.set, required this.onTap});

  final Widget leading;
  final String label;
  final bool set;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Material(
        color: palette.surface,
        shape: StadiumBorder(side: BorderSide(color: set ? palette.input : Colors.transparent)),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.only(left: 7, right: 14),
            child: Row(
              children: [
                SizedBox(width: 26, child: Center(child: leading)),
                const SizedBox(width: 6),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 140),
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, color: set ? palette.foreground : palette.muted),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// A sheet of your own accounts with balances. Returns the chosen account id.
Future<int?> pickAccount(BuildContext context, List<Account> accounts, {int? selected, String title = 'Choose account'}) {
  return showAppSheet<int>(
    context,
    title: title,
    builder: (context) {
      final palette = Palette.of(context);
      return ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.only(bottom: 20),
        children: [
          for (final account in accounts)
            ListRow(
              onTap: () => Navigator.pop(context, account.id),
              leading: CategoryIcon(glyph: accountIcon(account), color: account.id == selected ? 'blue' : 'slate'),
              title: account.name,
              subtitle: roleLabel(account),
              trailing: account.id == selected
                  ? Icon(LucideIcons.circleCheck, size: 22, color: palette.primary)
                  : (account.balance == null || account.currency == null
                        ? null
                        : MoneyText(account.balance!, account.currency!.code, decimals: account.currency!.decimals)),
            ),
        ],
      );
    },
  );
}

class _PayeeSheet extends ConsumerStatefulWidget {
  const _PayeeSheet({required this.type, required this.initial});

  final String type;
  final String initial;

  @override
  ConsumerState<_PayeeSheet> createState() => _PayeeSheetState();
}

class _PayeeSheetState extends ConsumerState<_PayeeSheet> {
  late final _controller = TextEditingController(text: widget.initial);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final accountType = widget.type == 'deposit' ? 'revenue' : 'expense';
    final query = _controller.text.trim().toLowerCase();
    final names = ref.watch(accountsProvider(accountType)).value?.map((account) => account.name).toList() ?? const <String>[];
    final frequent = (ref.watch(frequentProvider(widget.type)).value?.counterparties ?? const <AccountRef>[])
        .where((account) => account.type == accountType)
        .map((account) => account.name)
        .toList();
    final options = query.isEmpty ? frequent : names.where((name) => name.toLowerCase().contains(query)).take(8).toList();

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: TextField(
            controller: _controller,
            autofocus: true,
            textCapitalization: TextCapitalization.words,
            onChanged: (_) => setState(() {}),
            onSubmitted: (value) => Navigator.pop(context, value.trim()),
            decoration: InputDecoration(
              hintText: 'Name, or leave empty to use the title',
              prefixIcon: const Icon(LucideIcons.search, size: 18),
              suffixIcon: IconButton(
                icon: const Icon(LucideIcons.check, size: 18),
                onPressed: () => Navigator.pop(context, _controller.text.trim()),
              ),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Flexible(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.only(bottom: 20),
            children: [
              if (query.isEmpty && options.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 4),
                  child: Text(
                    'Frequent',
                    style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: palette.muted),
                  ),
                ),
              for (final name in options)
                ListRow(
                  leading: CategoryIcon(
                    glyph: widget.type == 'deposit' ? LucideIcons.briefcase : LucideIcons.store,
                    color: 'slate',
                    size: 40,
                  ),
                  title: name,
                  onTap: () => Navigator.pop(context, name),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DetailsSheet extends ConsumerStatefulWidget {
  const _DetailsSheet({required this.type, required this.budget, required this.tags, required this.notes});

  final String type;
  final int? budget;
  final String tags;
  final String notes;

  @override
  ConsumerState<_DetailsSheet> createState() => _DetailsSheetState();
}

class _DetailsSheetState extends ConsumerState<_DetailsSheet> {
  late int? _budget = widget.budget;
  late final _tags = TextEditingController(text: widget.tags);
  late final _notes = TextEditingController(text: widget.notes);

  @override
  void dispose() {
    _tags.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final budgets = widget.type == 'withdrawal' ? ref.watch(budgetsProvider).value ?? const <Budget>[] : const <Budget>[];
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (budgets.isNotEmpty) ...[
            const FieldLabel('Budget'),
            QuickPicks(
              items: [for (final budget in budgets) (label: budget.name, hint: null, active: _budget == budget.id, leading: null)],
              onPick: (index) => setState(() => _budget = _budget == budgets[index].id ? null : budgets[index].id),
            ),
            const SizedBox(height: 16),
          ],
          const FieldLabel('Tags'),
          TextField(
            controller: _tags,
            decoration: const InputDecoration(hintText: 'Separate with commas', prefixIcon: Icon(LucideIcons.tag, size: 18)),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Notes'),
          TextField(
            controller: _notes,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(hintText: 'Anything worth remembering'),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: () => Navigator.pop(context, (budget: _budget, tags: _tags.text.trim(), notes: _notes.text.trim())),
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }
}
