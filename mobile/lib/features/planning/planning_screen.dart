import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';

class PlanningScreen extends StatelessWidget {
  const PlanningScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Planning'),
          bottom: const TabBar(tabs: [Tab(text: 'Budgets'), Tab(text: 'Bills')]),
        ),
        body: const TabBarView(children: [_Budgets(), _Bills()]),
      ),
    );
  }
}

class _Budgets extends ConsumerWidget {
  const _Budgets();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(budgetsProvider.future),
      child: AsyncView(
        value: ref.watch(budgetsProvider),
        onRetry: () => ref.invalidate(budgetsProvider),
        builder: (budgets) => budgets.isEmpty
            ? ListView(children: const [
                MessageView(
                  icon: Icons.pie_chart_outline,
                  title: 'No budgets yet',
                  message: 'Budgets cap how much you spend on something each month. Create them in the web app.',
                ),
              ])
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: budgets.length,
                separatorBuilder: (_, _) => const SizedBox(height: 12),
                itemBuilder: (context, index) => _BudgetCard(budgets[index]),
              ),
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
    final limit = budget.limits.firstOrNull;
    final spent = double.tryParse(limit?.spent ?? budget.spent.firstOrNull?.value ?? '0')?.abs() ?? 0;
    final cap = double.tryParse(limit?.amount ?? '') ?? 0;
    final over = cap > 0 && spent > cap;
    final currency = limit?.currency.code ?? budget.spent.firstOrNull?.currency;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(child: Text(budget.name, style: Theme.of(context).textTheme.titleSmall)),
                if (currency != null)
                  Text(
                    cap > 0
                        ? '${formatMoney('$spent', currency)} of ${formatMoney('$cap', currency)}'
                        : '${formatMoney('$spent', currency)} spent',
                    style: TextStyle(color: over ? palette.expense : palette.muted),
                  ),
              ],
            ),
            if (cap > 0) ...[
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: (spent / cap).clamp(0, 1),
                  minHeight: 8,
                  color: over ? palette.expense : null,
                  backgroundColor: palette.surface,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                over ? '${formatMoney('${spent - cap}', currency!)} over' : '${formatMoney('${cap - spent}', currency!)} left',
                style: TextStyle(color: over ? palette.expense : palette.muted, fontSize: 13),
              ),
            ] else
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text('No limit set for this month', style: TextStyle(color: palette.muted, fontSize: 13)),
              ),
          ],
        ),
      ),
    );
  }
}

class _Bills extends ConsumerWidget {
  const _Bills();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () => ref.refresh(billsProvider.future),
      child: AsyncView(
        value: ref.watch(billsProvider),
        onRetry: () => ref.invalidate(billsProvider),
        builder: (bills) => bills.isEmpty
            ? ListView(children: const [
                MessageView(
                  icon: Icons.event_repeat_outlined,
                  title: 'No bills yet',
                  message: 'Bills track regular payments like rent and remind you when they are due. Create them in the web app.',
                ),
              ])
            : ListView.separated(
                padding: const EdgeInsets.symmetric(vertical: 8),
                itemCount: bills.length,
                separatorBuilder: (_, _) => const Divider(indent: 16, endIndent: 16),
                itemBuilder: (context, index) => _BillTile(bills[index]),
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
    final range = bill.amountMin == bill.amountMax
        ? formatMoney(bill.amountMin, bill.currency.code, decimals: bill.currency.decimals)
        : '${formatMoney(bill.amountMin, bill.currency.code, decimals: bill.currency.decimals)} to ${formatMoney(bill.amountMax, bill.currency.code, decimals: bill.currency.decimals)}';
    final status = bill.isPaid
        ? ('Paid', palette.income)
        : bill.isDue
            ? ('Due ${formatDay(bill.expectedDates.first)}', palette.expense)
            : (bill.nextExpected == null ? 'Not expected' : 'Next ${formatDay(bill.nextExpected!)}', palette.muted);

    return ListTile(
      title: Text(bill.name),
      subtitle: Text('$range · ${sentenceCase(bill.repeatFreq)}'),
      trailing: Text(status.$1, style: TextStyle(color: status.$2, fontWeight: FontWeight.w600)),
    );
  }
}
