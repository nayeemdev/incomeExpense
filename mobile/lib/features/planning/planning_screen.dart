import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';

enum _Tab { budgets, bills }

class PlanningScreen extends ConsumerStatefulWidget {
  const PlanningScreen({super.key});

  @override
  ConsumerState<PlanningScreen> createState() => _PlanningScreenState();
}

class _PlanningScreenState extends ConsumerState<PlanningScreen> {
  _Tab _tab = _Tab.budgets;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final budgets = _tab == _Tab.budgets;
    return Scaffold(
      appBar: AppBar(title: const Text('Budgets & bills')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => budgets ? ref.refresh(budgetsProvider.future) : ref.refresh(billsProvider.future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
              child: Segmented<_Tab>(
                options: const {_Tab.budgets: 'Budgets', _Tab.bills: 'Bills'},
                value: _tab,
                onChanged: (tab) => setState(() => _tab = tab),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 4),
              child: Text(
                budgets
                    ? 'Spending limits for this month and what is left in each.'
                    : 'Regular payments like rent, and whether they are paid this month.',
                style: TextStyle(color: palette.muted, fontSize: 13.5),
              ),
            ),
            budgets ? const _Budgets() : const _Bills(),
          ],
        ),
      ),
    );
  }
}

class _Budgets extends ConsumerWidget {
  const _Budgets();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categories = ref.watch(categoriesProvider).value ?? const <NamedRef>[];
    return AsyncView(
      value: ref.watch(budgetsProvider),
      onRetry: () => ref.invalidate(budgetsProvider),
      builder: (budgets) => budgets.isEmpty
          ? const EmptyState(
              icon: LucideIcons.chartPie,
              title: 'No budgets yet',
              message: 'Budgets cap how much you spend on something each month. Create them in the web app.',
            )
          : Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                children: [
                  for (final budget in budgets)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: _BudgetCard(budget, style: categories.where((category) => category.name == budget.name).firstOrNull),
                    ),
                ],
              ),
            ),
    );
  }
}

class _BudgetCard extends StatelessWidget {
  const _BudgetCard(this.budget, {this.style});

  final Budget budget;
  final NamedRef? style;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final limit = budget.limits.firstOrNull;
    final spent = double.tryParse(limit?.spent ?? budget.spent.firstOrNull?.value ?? '0')?.abs() ?? 0;
    final cap = double.tryParse(limit?.amount ?? '') ?? 0;
    final over = cap > 0 && spent > cap;
    final currency = limit?.currency.code ?? budget.spent.firstOrNull?.currency;
    final muted = text.bodySmall?.copyWith(color: palette.muted);

    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              style == null
                  ? const CategoryIcon(glyph: LucideIcons.chartPie, color: 'sky', size: 42)
                  : CategoryIcon(icon: style!.icon, color: style!.color, size: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(budget.name, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                    if (currency != null && cap > 0) Text('${(spent / cap * 100).round()}% used', style: muted),
                  ],
                ),
              ),
              if (currency != null) MoneyText('$spent', currency, tone: MoneyTone.none),
            ],
          ),
          const SizedBox(height: 14),
          if (cap > 0 && currency != null) ...[
            ProgressBar(value: spent, max: cap),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Text(
                    over ? '${formatMoney('${spent - cap}', currency)} over' : '${formatMoney('${cap - spent}', currency)} left',
                    style: muted?.copyWith(color: over ? palette.negative : null, fontWeight: FontWeight.w600),
                  ),
                ),
                Text('of ${formatMoney('$cap', currency)}', style: muted),
              ],
            ),
          ] else
            Text('No limit set for this month', style: muted),
        ],
      ),
    );
  }
}

class _Bills extends ConsumerWidget {
  const _Bills();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AsyncView(
      value: ref.watch(billsProvider),
      onRetry: () => ref.invalidate(billsProvider),
      builder: (bills) => bills.isEmpty
          ? const EmptyState(
              icon: LucideIcons.receipt,
              title: 'No bills yet',
              message: 'Bills track regular payments like rent and remind you when they are due. Create them in the web app.',
            )
          : Column(children: [for (final bill in bills) _BillTile(bill)]),
    );
  }
}

class _BillTile extends StatelessWidget {
  const _BillTile(this.bill);

  final Bill bill;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final code = bill.currency.code;
    final decimals = bill.currency.decimals;
    final range = bill.amountMin == bill.amountMax
        ? formatMoney(bill.amountMin, code, decimals: decimals)
        : '${formatMoney(bill.amountMin, code, decimals: decimals)} to ${formatMoney(bill.amountMax, code, decimals: decimals)}';
    final (label, foreground, background) = bill.isPaid
        ? ('Paid', palette.positive, palette.positiveTint)
        : bill.isDue
        ? ('Due ${formatDay(bill.expectedDates.first)}', palette.negative, palette.negativeTint)
        : (bill.nextExpected == null ? 'Not expected' : formatDay(bill.nextExpected!), palette.muted, palette.surface);

    return ListRow(
      leading: CategoryIcon(glyph: bill.isPaid ? LucideIcons.circleCheck : LucideIcons.receipt, color: bill.isPaid ? 'emerald' : 'indigo'),
      title: bill.name,
      subtitle: '$range · ${sentenceCase(bill.repeatFreq)}',
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(99)),
        child: Text(
          label,
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: foreground),
        ),
      ),
    );
  }
}
