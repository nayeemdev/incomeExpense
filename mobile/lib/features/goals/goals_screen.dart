import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';

const _goalColors = ['sky', 'teal', 'amber', 'emerald', 'indigo', 'rose', 'cyan', 'orange'];

String goalColor(int index) => _goalColors[index % _goalColors.length];

class GoalsScreen extends ConsumerWidget {
  const GoalsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final goals = ref.watch(goalsProvider);
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Goals'),
        automaticallyImplyLeading: false,
        actions: [
          if (canEdit) TextButton(onPressed: () => showGoalSheet(context, ref), child: const Text('Add new')),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(goalsProvider.future),
        child: AsyncView(
          value: goals,
          onRetry: () => ref.invalidate(goalsProvider),
          builder: (goals) => goals.isEmpty
              ? ListView(
                  children: [
                    const SizedBox(height: 60),
                    EmptyState(
                      icon: LucideIcons.target,
                      title: 'No goals yet',
                      message: 'Set a goal, like a holiday or an emergency fund, and watch it fill up.',
                      action: canEdit
                          ? FilledButton(onPressed: () => showGoalSheet(context, ref), child: const Text('Create a goal'))
                          : null,
                    ),
                  ],
                )
              : _GoalsBody(goals),
        ),
      ),
    );
  }
}

class _GoalsBody extends StatelessWidget {
  const _GoalsBody(this.goals);

  final List<Goal> goals;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final saved = goals.fold<double>(0, (sum, goal) => sum + goal.saved);
    final target = goals.fold<double>(0, (sum, goal) => sum + (goal.target ?? goal.saved));
    final currency = goals.first.currency.code;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
      children: [
        SurfaceCard(
          padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
          child: Column(
            children: [
              Gauge(
                progress: target == 0 ? 0 : saved / target,
                child: Column(
                  children: [
                    Text('Saved so far', style: TextStyle(color: palette.muted, fontSize: 13)),
                    const SizedBox(height: 4),
                    BigAmount('$saved', currency, size: 30),
                  ],
                ),
              ),
              Text(
                '${target == 0 ? 0 : (saved / target * 100).round()}% of ${formatMoney('$target', currency)} across ${goals.length} goals',
                style: TextStyle(color: palette.muted, fontSize: 13),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: goals.length,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.05,
          ),
          itemBuilder: (context, index) => _GoalTile(goals[index], color: goalColor(index)),
        ),
      ],
    );
  }
}

class _GoalTile extends StatelessWidget {
  const _GoalTile(this.goal, {required this.color});

  final Goal goal;
  final String color;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final tint = categoryColor(color);
    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      onTap: () => context.push('/goals/${goal.id}', extra: color),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CategoryIcon(glyph: LucideIcons.target, color: color, size: 36),
              const Spacer(),
              if (goal.target != null)
                Text(
                  '${(goal.progress * 100).round()}%',
                  style: TextStyle(fontWeight: FontWeight.w700, color: tint),
                ),
            ],
          ),
          const Spacer(),
          Text(
            goal.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 13, color: palette.muted),
          ),
          const SizedBox(height: 2),
          Align(alignment: Alignment.centerLeft, child: BigAmount('${goal.saved}', goal.currency.code, size: 20)),
          const SizedBox(height: 10),
          ProgressBar(value: goal.saved, max: goal.target ?? goal.saved, color: tint, height: 6),
        ],
      ),
    );
  }
}

class GoalScreen extends ConsumerWidget {
  const GoalScreen({super.key, required this.goalId, this.color = 'sky'});

  final int goalId;
  final String color;

  Future<void> _delete(BuildContext context, WidgetRef ref, Goal goal) async {
    final confirmed = await showConfirm(
      context,
      icon: LucideIcons.trash2,
      title: 'Delete this goal?',
      message: 'The money stays in your accounts. Only the goal and its history are removed.',
      confirm: 'Delete',
    );
    if (!confirmed || !context.mounted) return;
    try {
      await ref.read(apiProvider).delete(ledgerPath(ref.read(currentLedgerProvider), 'piggy-banks/${goal.id}'));
      ref.invalidate(goalsProvider);
      if (context.mounted) context.pop();
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final goal = ref.watch(goalsProvider).value?.where((goal) => goal.id == goalId).firstOrNull;
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Goal details'),
        actions: [
          if (goal != null && canEdit)
            IconButton(tooltip: 'Delete', icon: const Icon(LucideIcons.trash2, size: 20), onPressed: () => _delete(context, ref, goal)),
          const SizedBox(width: 4),
        ],
      ),
      body: goal == null
          ? const SkeletonList(rows: 3)
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                SurfaceCard(
                  padding: const EdgeInsets.fromLTRB(16, 24, 16, 20),
                  child: Column(
                    children: [
                      Gauge(
                        progress: goal.progress,
                        child: Column(
                          children: [
                            Text(goal.name, style: TextStyle(color: palette.muted, fontSize: 13)),
                            const SizedBox(height: 4),
                            BigAmount('${goal.saved}', goal.currency.code, size: 32),
                          ],
                        ),
                      ),
                      Text(
                        goal.target == null
                            ? 'No target set'
                            : '${(goal.progress * 100).round()}% of ${formatMoney('${goal.target}', goal.currency.code)}',
                        style: TextStyle(color: palette.muted, fontSize: 13),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                SurfaceCard(
                  child: Column(
                    children: [
                      if (goal.target != null)
                        _Fact('Left to save', formatMoney('${(goal.target! - goal.saved).clamp(0, double.infinity)}', goal.currency.code)),
                      if (goal.targetDate != null) _Fact('Target date', DateFormat('d MMM y').format(goal.targetDate!)),
                      _Fact('Saved in', goal.accounts.map((account) => account.name).join(', ')),
                    ],
                  ),
                ),
                if (canEdit) ...[
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: goal.saved <= 0 ? null : () => _move(context, ref, goal, add: false),
                          icon: const Icon(LucideIcons.minus, size: 18),
                          label: const Text('Withdraw'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: () => _move(context, ref, goal, add: true),
                          icon: const Icon(LucideIcons.plus, size: 18),
                          label: const Text('Add money'),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
    );
  }

  Future<void> _move(BuildContext context, WidgetRef ref, Goal goal, {required bool add}) async {
    final amount = await showAppSheet<String>(
      context,
      title: add ? 'Add to ${goal.name}' : 'Withdraw from ${goal.name}',
      builder: (_) => _AmountSheet(currency: goal.currency),
    );
    if (amount == null || !context.mounted) return;
    try {
      await ref.read(apiProvider).post(ledgerPath(ref.read(currentLedgerProvider), 'piggy-banks/${goal.id}/move'), {
        'account_id': goal.accounts.first.id,
        'amount': add ? amount : '-$amount',
      });
      ref.invalidate(goalsProvider);
      if (context.mounted) showToast(context, add ? 'Added to ${goal.name}' : 'Withdrawn from ${goal.name}');
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }
}

class _Fact extends StatelessWidget {
  const _Fact(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Text(label, style: TextStyle(color: Palette.of(context).muted)),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

/// A keypad sheet that returns the amount entered.
class _AmountSheet extends StatefulWidget {
  const _AmountSheet({required this.currency});

  final Currency currency;

  @override
  State<_AmountSheet> createState() => _AmountSheetState();
}

class _AmountSheetState extends State<_AmountSheet> {
  String _amount = '';

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(child: BigAmount(_amount.isEmpty ? '0' : _amount, widget.currency.code, size: 40, decimals: widget.currency.decimals)),
          const SizedBox(height: 20),
          AmountKeypad(onKey: (key) => setState(() => _amount = applyKey(_amount, key, decimals: widget.currency.decimals))),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: (double.tryParse(_amount) ?? 0) > 0 ? () => Navigator.pop(context, _amount) : null,
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
  }
}

Future<void> showGoalSheet(BuildContext context, WidgetRef ref) async {
  final created = await showAppSheet<bool>(context, title: 'New goal', builder: (_) => const _GoalForm());
  if (created == true && context.mounted) {
    await showSuccess(context, title: 'Goal added', message: 'Your new goal is ready. Add money to it whenever you like.');
  }
}

class _GoalForm extends ConsumerStatefulWidget {
  const _GoalForm();

  @override
  ConsumerState<_GoalForm> createState() => _GoalFormState();
}

class _GoalFormState extends ConsumerState<_GoalForm> {
  final _name = TextEditingController();
  final _target = TextEditingController();
  int? _accountId;
  DateTime? _date;
  bool _saving = false;

  @override
  void dispose() {
    _name.dispose();
    _target.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final ledger = ref.read(currentLedgerProvider);
    setState(() => _saving = true);
    try {
      await ref.read(apiProvider).post(ledgerPath(ledger, 'piggy-banks'), {
        'name': _name.text.trim(),
        'currency_id': ledger.currency.id,
        'target_amount': _target.text.trim().isEmpty ? null : _target.text.trim(),
        'target_date': _date == null ? null : isoDate(_date!),
        'account_ids': [_accountId],
      });
      ref.invalidate(goalsProvider);
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
    final accounts = ref.watch(accountsProvider('asset')).value ?? const <Account>[];
    _accountId ??= accounts.where((account) => account.role == 'savings').firstOrNull?.id ?? accounts.firstOrNull?.id;
    final ready = _name.text.trim().isNotEmpty && _accountId != null && !_saving;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const FieldLabel('Name', required: true),
          TextField(
            controller: _name,
            autofocus: true,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(hintText: 'Emergency fund, new laptop...'),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Target amount'),
          TextField(
            controller: _target,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(hintText: '0.00', suffixText: ref.watch(currentLedgerProvider).currency.code),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Save in', required: true),
          QuickPicks(
            items: [for (final account in accounts) (label: account.name, hint: null, active: account.id == _accountId, leading: null)],
            onPick: (index) => setState(() => _accountId = accounts[index].id),
          ),
          const SizedBox(height: 16),
          const FieldLabel('Target date'),
          Material(
            color: palette.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _date ?? DateTime.now().add(const Duration(days: 90)),
                  firstDate: DateTime.now(),
                  lastDate: DateTime(2100),
                );
                if (picked != null) setState(() => _date = picked);
              },
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(LucideIcons.calendar, size: 18, color: palette.subtle),
                    const SizedBox(width: 10),
                    Text(
                      _date == null ? 'Optional' : formatDate(_date!),
                      style: TextStyle(color: _date == null ? palette.subtle : palette.foreground),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: ready ? _save : null, child: Text(_saving ? 'Creating...' : 'Create goal')),
        ],
      ),
    );
  }
}
