import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:home_widget/home_widget.dart';

import '../../api/models.dart';
import '../../shared/format.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';

const appGroup = 'group.com.nayeemdev.ledgerfly';
const _widgetKind = 'LedgerFlyWidget';

/// Copies this month's numbers into the App Group the home and lock screen widgets read from.
final widgetSyncProvider = Provider.autoDispose<void>((ref) {
  final ledger = ref.watch(currentLedgerProvider);
  final summary = ref.watch(summaryProvider).value;
  final budgets = ref.watch(budgetsProvider).value;
  final bills = ref.watch(billsProvider).value;
  if (summary == null || budgets == null || bills == null) return;
  _write(ledger, summary, budgets, bills);
});

Future<void> _write(Ledger ledger, Summary summary, List<Budget> budgets, List<Bill> bills) async {
  final currency = ledger.currency.code;
  final limits = budgets.expand((budget) => budget.limits).where((limit) => limit.currency.code == currency);
  final budgeted = limits.fold<double>(0, (sum, limit) => sum + (double.tryParse(limit.amount) ?? 0));
  final used = limits.fold<double>(0, (sum, limit) => sum + (double.tryParse(limit.spent ?? '0') ?? 0).abs());
  final due = bills.where((bill) => bill.isDue).toList()..sort((a, b) => a.expectedDates.first.compareTo(b.expectedDates.first));
  final next = due.firstOrNull;

  await HomeWidget.setAppGroupId(appGroup);
  await Future.wait([
    HomeWidget.saveWidgetData('signed_in', true),
    HomeWidget.saveWidgetData('ledger', ledger.name),
    HomeWidget.saveWidgetData('spent', formatMoney(summary.spent.value, summary.spent.currency)),
    HomeWidget.saveWidgetData('earned', formatMoney(summary.earned.value, summary.earned.currency)),
    HomeWidget.saveWidgetData('budget_left', budgeted == 0 ? null : formatMoney('${budgeted - used}', currency)),
    HomeWidget.saveWidgetData('budget_progress', budgeted == 0 ? null : (used / budgeted).clamp(0, 1).toDouble()),
    HomeWidget.saveWidgetData('bill_name', next?.name),
    HomeWidget.saveWidgetData('bill_amount', next == null ? null : formatMoney(next.amountMax, next.currency.code)),
    HomeWidget.saveWidgetData('bill_due', next == null ? null : formatDay(next.expectedDates.first)),
  ]);
  await HomeWidget.updateWidget(iOSName: _widgetKind);
}

/// Hides the numbers on the widgets once nobody is signed in.
Future<void> clearWidget() async {
  await HomeWidget.setAppGroupId(appGroup);
  await HomeWidget.saveWidgetData('signed_in', false);
  await HomeWidget.updateWidget(iOSName: _widgetKind);
}
