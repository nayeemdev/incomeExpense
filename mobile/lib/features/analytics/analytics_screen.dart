import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';
import '../transactions/transaction_tile.dart';

const _ranges = {ReportRange.week: 'This week', ReportRange.month: 'This month', ReportRange.year: 'This year'};

class AnalyticsScreen extends ConsumerStatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  ConsumerState<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends ConsumerState<AnalyticsScreen> {
  bool _expense = true;
  ReportRange _range = ReportRange.week;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final report = ref.watch(reportProvider(_range));
    final type = _expense ? 'withdrawal' : 'deposit';
    final history = ref.watch(transactionsProvider((query: '', type: type, accountId: null)));

    return Scaffold(
      appBar: AppBar(title: const Text('Analytics'), automaticallyImplyLeading: false),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.refresh(reportProvider(_range).future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 120),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
              child: Segmented<bool>(
                options: const {true: 'Expense', false: 'Income'},
                value: _expense,
                colors: {true: palette.negative, false: palette.primary},
                onChanged: (value) => setState(() => _expense = value),
              ),
            ),
            AsyncView(
              value: report,
              onRetry: () => ref.invalidate(reportProvider(_range)),
              loading: const Padding(
                padding: EdgeInsets.all(20),
                child: Column(children: [Skeleton(height: 40, width: 200), SizedBox(height: 20), Skeleton(height: 200, radius: 20)]),
              ),
              builder: (report) =>
                  _ReportBody(report: report, expense: _expense, range: _range, onRange: (range) => setState(() => _range = range)),
            ),
            if (_expense) const _Budgets(),
            SectionHeader(_expense ? 'Recent expenses' : 'Recent income', action: 'See all', onAction: () => context.push('/transactions')),
            switch (history) {
              AsyncValue(hasValue: true, :final value?) => Column(
                children: [for (final item in value.items.take(5)) TransactionTile(item)],
              ),
              _ => const SkeletonList(rows: 3),
            },
          ],
        ),
      ),
    );
  }
}

class _ReportBody extends ConsumerWidget {
  const _ReportBody({required this.report, required this.expense, required this.range, required this.onRange});

  final Report report;
  final bool expense;
  final ReportRange range;
  final ValueChanged<ReportRange> onRange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final values = [for (final bucket in report.buckets) expense ? bucket.expense : bucket.income];
    final total = values.fold<double>(0, (a, b) => a + b);
    final interval = range == ReportRange.year ? 'month' : 'day';
    final labels = [
      for (final bucket in report.buckets) range == ReportRange.week ? _weekday(bucket.start) : bucketLabel(bucket.start, interval),
    ];
    final categories = expense ? report.expenseCategories : report.incomeCategories;
    final styles = ref.watch(categoryStyleById);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(expense ? 'Total spent' : 'Total earned', style: TextStyle(color: palette.muted, fontSize: 13.5)),
                    const SizedBox(height: 4),
                    Align(alignment: Alignment.centerLeft, child: BigAmount('$total', report.currency, size: 32)),
                  ],
                ),
              ),
              PopupMenuButton<ReportRange>(
                initialValue: range,
                onSelected: onRange,
                color: palette.card,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                itemBuilder: (context) => [
                  for (final MapEntry(:key, :value) in _ranges.entries) PopupMenuItem(value: key, child: Text(value)),
                ],
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: palette.surface, borderRadius: BorderRadius.circular(99)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_ranges[range]!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                      const SizedBox(width: 4),
                      Icon(LucideIcons.chevronDown, size: 16, color: palette.muted),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 0),
          child: SurfaceCard(
            padding: const EdgeInsets.fromLTRB(10, 16, 10, 8),
            child: BarChart(values: values, labels: labels, currency: report.currency),
          ),
        ),
        if (categories.isNotEmpty) ...[
          const SectionHeader('By category'),
          for (final row in categories.take(6))
            Builder(
              builder: (context) {
                final style = row.id == null ? null : styles[row.id];
                final value = double.tryParse(row.amount.value) ?? 0;
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  child: Row(
                    children: [
                      CategoryIcon(icon: style?.icon, color: style?.color, size: 42),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(child: Text(row.name ?? 'No category', style: Theme.of(context).textTheme.titleSmall)),
                                MoneyText(
                                  row.amount.value,
                                  row.amount.currency,
                                  tone: MoneyTone.none,
                                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            ProgressBar(value: value, max: total == 0 ? 1 : total, color: categoryColor(style?.color), height: 6),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ],
    );
  }

  String _weekday(DateTime date) => const ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][date.weekday - 1];
}

class _Budgets extends ConsumerWidget {
  const _Budgets();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final budgets = (ref.watch(budgetsProvider).value ?? const <Budget>[]).where((budget) => budget.limits.isNotEmpty).toList();
    if (budgets.isEmpty) return const SizedBox.shrink();
    final palette = Palette.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader('Budgets', action: 'Manage', onAction: () => context.push('/planning')),
        SizedBox(
          height: 112,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: budgets.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final budget = budgets[index];
              final limit = budget.limits.first;
              final spent = double.tryParse(limit.spent ?? '0')?.abs() ?? 0;
              final cap = double.tryParse(limit.amount) ?? 0;
              final left = cap - spent;
              return SizedBox(
                width: 180,
                child: SurfaceCard(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(budget.name, style: Theme.of(context).textTheme.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                      const Spacer(),
                      MoneyText('${left.abs()}', limit.currency.code, tone: left < 0 ? MoneyTone.negative : MoneyTone.none),
                      Text(left < 0 ? 'over budget' : 'left of ${limit.amount}', style: TextStyle(fontSize: 12, color: palette.muted)),
                      const SizedBox(height: 8),
                      ProgressBar(value: spent, max: cap, height: 6),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
