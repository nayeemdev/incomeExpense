import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
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
    final budgets = _tab == _Tab.budgets;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator.adaptive(
          onRefresh: () => budgets ? ref.refresh(budgetsProvider.future) : ref.refresh(billsProvider.future),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 110),
            children: [
              PageHeader(
                title: 'Planning',
                description: budgets
                    ? 'Spending limits for this month and what is left in each.'
                    : 'Regular payments like rent, and whether they are paid this month.',
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: Segmented<_Tab>(
                  options: const {_Tab.budgets: 'Budgets', _Tab.bills: 'Bills'},
                  value: _tab,
                  onChanged: (tab) => setState(() => _tab = tab),
                ),
              ),
              Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: budgets ? const _Budgets() : const _Bills()),
            ],
          ),
        ),
      ),
    );
  }
}

const _listSkeleton = SurfaceCard(child: Column(children: [SkeletonRow(), SkeletonRow(), SkeletonRow(), SkeletonRow()]));

class _Budgets extends ConsumerWidget {
  const _Budgets();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AsyncView(
      value: ref.watch(budgetsProvider),
      onRetry: () => ref.invalidate(budgetsProvider),
      loading: _listSkeleton,
      builder: (budgets) => budgets.isEmpty
          ? const SurfaceCard(
              child: EmptyState(
                icon: LucideIcons.chartPie,
                title: 'No budgets yet',
                message: 'Budgets cap how much you spend on something each month. Create them in the web app.',
              ),
            )
          : Column(
              children: [for (final budget in budgets) Padding(padding: const EdgeInsets.only(bottom: 12), child: _BudgetCard(budget))],
            ),
    );
  }
}

class _BudgetCard extends StatelessWidget {
  const _BudgetCard(this.budget);

  final Budget budget;

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
              IconTile(LucideIcons.chartPie, tone: over ? Tone.negative : Tone.brand, size: 34),
              const SizedBox(width: 12),
              Expanded(child: Text(budget.name, style: text.titleSmall)),
              if (currency != null)
                MoneyText(
                  '$spent',
                  currency,
                  tone: MoneyTone.none,
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (cap > 0 && currency != null) ...[
            ProgressBar(value: spent, max: cap),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: Text(
                    over ? '${formatMoney('${spent - cap}', currency)} over' : '${formatMoney('${cap - spent}', currency)} left',
                    style: muted?.copyWith(color: over ? palette.negative : null, fontWeight: over ? FontWeight.w600 : null),
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
      loading: _listSkeleton,
      builder: (bills) => SurfaceCard(
        child: bills.isEmpty
            ? const EmptyState(
                icon: LucideIcons.receipt,
                title: 'No bills yet',
                message: 'Bills track regular payments like rent and remind you when they are due. Create them in the web app.',
              )
            : Column(
                children: [
                  for (final (index, bill) in bills.indexed) ...[if (index > 0) const Divider(indent: 64), _BillTile(bill)],
                ],
              ),
      ),
    );
  }
}

class _BillTile extends StatelessWidget {
  const _BillTile(this.bill);

  final Bill bill;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final code = bill.currency.code;
    final decimals = bill.currency.decimals;
    final range = bill.amountMin == bill.amountMax
        ? formatMoney(bill.amountMin, code, decimals: decimals)
        : '${formatMoney(bill.amountMin, code, decimals: decimals)} to ${formatMoney(bill.amountMax, code, decimals: decimals)}';
    final (label, tone) = bill.isPaid
        ? ('Paid', Tone.positive)
        : bill.isDue
        ? ('Due ${formatDay(bill.expectedDates.first)}', Tone.negative)
        : (bill.nextExpected == null ? 'Not expected' : formatDay(bill.nextExpected!), Tone.neutral);
    final colors = ToneColors.of(context, tone);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          IconTile(bill.isPaid ? LucideIcons.circleCheck : LucideIcons.calendarClock, tone: tone, size: 34),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(bill.name, style: text.titleSmall),
                Text('$range · ${sentenceCase(bill.repeatFreq)}', style: text.bodySmall?.copyWith(color: palette.muted)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: colors.background, borderRadius: BorderRadius.circular(99)),
            child: Text(
              label,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: colors.foreground),
            ),
          ),
        ],
      ),
    );
  }
}
