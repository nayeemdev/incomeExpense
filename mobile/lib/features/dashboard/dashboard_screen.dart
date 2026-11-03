import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../accounts/accounts_screen.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../notifications/notifications_screen.dart';
import '../transactions/transaction_tile.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ledger = ref.watch(currentLedgerProvider);
    final summary = ref.watch(summaryProvider);
    final recent = ref.watch(transactionsProvider((query: '', type: null, accountId: null)));
    final unread = ref.watch(unreadCountProvider).value ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(ledger.name),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => context.push('/notifications'),
            icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
          ),
          IconButton(tooltip: 'Settings', onPressed: () => context.push('/settings'), icon: const Icon(Icons.settings_outlined)),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          refreshLedgerData(ref);
          ref.invalidate(unreadCountProvider);
          await ref.read(summaryProvider.future);
        },
        child: AsyncView(
          value: summary,
          onRetry: () => ref.invalidate(summaryProvider),
          builder: (summary) => ListView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 96),
            children: [
              Text(DateFormat('MMMM y').format(DateTime.now()), style: TextStyle(color: Palette.of(context).muted)),
              const SizedBox(height: 12),
              _NetWorthCard(summary.netWorth),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(child: _StatCard('Spent', summary.spent, Palette.of(context).expense)),
                  const SizedBox(width: 12),
                  Expanded(child: _StatCard('Earned', summary.earned, Palette.of(context).income)),
                ],
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Accounts',
                trailing: TextButton(onPressed: () => context.go('/accounts'), child: const Text('All')),
                child: Column(children: [for (final account in summary.accounts) AccountTile(account)]),
              ),
              if (summary.topCategories.isNotEmpty) ...[
                const SizedBox(height: 16),
                SectionCard(title: 'Top spending', child: _TopCategories(summary.topCategories)),
              ],
              const SizedBox(height: 16),
              SectionCard(
                title: 'Recent transactions',
                trailing: TextButton(onPressed: () => context.go('/transactions'), child: const Text('All')),
                child: Column(
                  children: [
                    for (final transaction in recent.value?.items.take(6) ?? const <Transaction>[]) TransactionTile(transaction),
                    if (recent.isLoading) const Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NetWorthCard extends StatelessWidget {
  const _NetWorthCard(this.amount);

  final Amount amount;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Net worth', style: TextStyle(color: Palette.of(context).muted)),
            const SizedBox(height: 4),
            Text(
              formatMoney(amount.value, amount.currency),
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard(this.label, this.amount, this.color);

  final String label;
  final Amount amount;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(color: Palette.of(context).muted)),
            const SizedBox(height: 4),
            FittedBox(
              child: Text(
                formatMoney(amount.value, amount.currency),
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600, color: color),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TopCategories extends StatelessWidget {
  const _TopCategories(this.rows);

  final List<CategoryTotal> rows;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final largest = rows.map((row) => double.tryParse(row.amount.value) ?? 0).fold<double>(0, (a, b) => a > b ? a : b);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
      child: Column(
        children: [
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(row.name ?? 'No category')),
                      Text(formatMoney(row.amount.value, row.amount.currency), style: const TextStyle(fontWeight: FontWeight.w600)),
                    ],
                  ),
                  const SizedBox(height: 6),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: largest == 0 ? 0 : (double.tryParse(row.amount.value) ?? 0) / largest,
                      minHeight: 6,
                      backgroundColor: palette.surface,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
