import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

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
    final unread = ref.watch(unreadCountProvider).value ?? 0;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator.adaptive(
          onRefresh: () async {
            refreshLedgerData(ref);
            ref.invalidate(unreadCountProvider);
            await ref.read(summaryProvider.future);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 110),
            children: [
              PageHeader(
                title: DateFormat('MMMM y').format(DateTime.now()),
                description: '${ledger.name}: how this month is going at a glance.',
                actions: [
                  SquareIconButton(
                    icon: LucideIcons.bell,
                    tooltip: 'Notifications',
                    badge: unread,
                    onPressed: () => context.push('/notifications'),
                  ),
                  SquareIconButton(icon: LucideIcons.settings, tooltip: 'Settings', onPressed: () => context.push('/settings')),
                ],
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: AsyncView(
                  value: summary,
                  onRetry: () => ref.invalidate(summaryProvider),
                  loading: const _DashboardSkeleton(),
                  builder: (summary) => _DashboardBody(summary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DashboardBody extends ConsumerWidget {
  const _DashboardBody(this.summary);

  final Summary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final recent = ref.watch(transactionsProvider((query: '', type: null, accountId: null)));
    String money(Amount amount) => formatMoney(amount.value, amount.currency);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        StatCard(label: 'Net worth', value: money(summary.netWorth), icon: LucideIcons.landmark, tone: Tone.brand),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: StatCard(
                label: 'Spent',
                value: money(summary.spent),
                icon: LucideIcons.arrowUpRight,
                tone: Tone.negative,
                compact: true,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: StatCard(
                label: 'Earned',
                value: money(summary.earned),
                icon: LucideIcons.arrowDownLeft,
                tone: Tone.positive,
                compact: true,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        SectionCard(
          title: 'Accounts',
          action: TextButton(onPressed: () => context.go('/accounts'), child: const Text('View all')),
          child: Column(
            children: [
              for (final (index, account) in summary.accounts.indexed) ...[
                if (index > 0) const Divider(indent: 64),
                AccountTile(account, showIcon: true),
              ],
            ],
          ),
        ),
        if (summary.topCategories.isNotEmpty) ...[
          const SizedBox(height: 16),
          SectionCard(title: 'Top spending', description: 'Where the money went this month', child: _TopCategories(summary.topCategories)),
        ],
        const SizedBox(height: 16),
        SectionCard(
          title: 'Recent transactions',
          action: TextButton(onPressed: () => context.go('/transactions'), child: const Text('View all')),
          child: switch (recent) {
            AsyncValue(hasValue: true, :final value?) when value.items.isEmpty => const EmptyState(
              icon: LucideIcons.receipt,
              title: 'Nothing yet',
              message: 'Tap + to record your first transaction.',
            ),
            AsyncValue(hasValue: true, :final value?) => Column(
              children: [
                for (final (index, transaction) in value.items.take(6).indexed) ...[
                  if (index > 0) const Divider(indent: 60),
                  TransactionTile(transaction),
                ],
              ],
            ),
            _ => const Column(children: [SkeletonRow(), SkeletonRow(), SkeletonRow()]),
          },
        ),
      ],
    );
  }
}

class _TopCategories extends StatelessWidget {
  const _TopCategories(this.rows);

  final List<CategoryTotal> rows;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final values = rows.map((row) => double.tryParse(row.amount.value) ?? 0).toList();
    final largest = values.fold<double>(0, (a, b) => a > b ? a : b);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: Column(
        children: [
          for (final (index, row) in rows.indexed)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(row.name ?? 'No category', style: text.bodyMedium)),
                      MoneyText(
                        row.amount.value,
                        row.amount.currency,
                        tone: MoneyTone.none,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  ProgressBar(value: values[index], max: largest == 0 ? 1 : largest),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    Widget stat({double valueWidth = 160}) => SurfaceCard(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(children: [Skeleton(width: 70, height: 13), Spacer(), Skeleton(width: 34, height: 34, radius: 9)]),
          const SizedBox(height: 10),
          Skeleton(width: valueWidth, height: 26),
        ],
      ),
    );
    return Column(
      children: [
        stat(),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: stat(valueWidth: 90)),
            const SizedBox(width: 12),
            Expanded(child: stat(valueWidth: 90)),
          ],
        ),
        const SizedBox(height: 16),
        SurfaceCard(
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  border: Border(bottom: BorderSide(color: Palette.of(context).border)),
                ),
                child: const Row(children: [Skeleton(width: 90, height: 15)]),
              ),
              const SkeletonRow(),
              const SkeletonRow(),
              const SkeletonRow(),
            ],
          ),
        ),
      ],
    );
  }
}
