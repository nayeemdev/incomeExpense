import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../accounts/accounts_screen.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';
import '../notifications/notifications_screen.dart';
import '../transactions/outbox.dart';
import '../transactions/transaction_tile.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ledger = ref.watch(currentLedgerProvider);
    final summary = ref.watch(summaryProvider);
    final user = ref.watch(userProvider).value;
    final unread = ref.watch(unreadCountProvider).value ?? 0;
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator.adaptive(
          onRefresh: () async {
            await ref.read(outboxProvider.notifier).flush();
            refreshLedgerData(ref.invalidate);
            ref.invalidate(unreadCountProvider);
            await ref.read(summaryProvider.future);
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 120),
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 16, 0),
                child: Row(
                  children: [
                    const LogoMark(size: 40),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(user == null ? 'Welcome back' : 'Hi, ${user.name.split(' ').first}', style: text.titleMedium),
                          Text(ledger.name, style: text.bodySmall?.copyWith(color: palette.muted)),
                        ],
                      ),
                    ),
                    RoundIconButton(
                      icon: LucideIcons.bell,
                      tooltip: 'Notifications',
                      badge: unread,
                      onPressed: () => context.push('/notifications'),
                    ),
                  ],
                ),
              ),
              const _OutboxBanner(),
              AsyncView(
                value: summary,
                onRetry: () => ref.invalidate(summaryProvider),
                loading: const _HomeSkeleton(),
                builder: (summary) => _HomeBody(summary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HomeBody extends ConsumerWidget {
  const _HomeBody(this.summary);

  final Summary summary;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final recent = ref.watch(transactionsProvider((query: '', type: null, accountId: null)));
    final goals = ref.watch(goalsProvider).value ?? const <Goal>[];
    final portfolio = ref.watch(portfolioProvider).value;
    final net = (double.tryParse(summary.earned.value) ?? 0) - (double.tryParse(summary.spent.value) ?? 0);
    final currency = summary.netWorth.currency;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 28),
        Text(
          'Total balance',
          textAlign: TextAlign.center,
          style: TextStyle(color: palette.muted, fontSize: 14),
        ),
        const SizedBox(height: 6),
        Center(child: BigAmount(summary.netWorth.value, currency, size: 42)),
        const SizedBox(height: 10),
        Center(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: net >= 0 ? palette.positiveTint : palette.negativeTint,
              borderRadius: BorderRadius.circular(99),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  net >= 0 ? LucideIcons.trendingUp : LucideIcons.trendingDown,
                  size: 14,
                  color: net >= 0 ? palette.positive : palette.negative,
                ),
                const SizedBox(width: 5),
                Text(
                  '${formatMoney('$net', currency, signed: true)} this month',
                  style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: net >= 0 ? palette.positive : palette.negative),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              Expanded(
                child: _FlowButton(
                  label: 'Expense',
                  amount: formatMoney(summary.spent.value, currency),
                  icon: LucideIcons.arrowUpRight,
                  color: palette.negative,
                  tint: palette.negativeTint,
                  onTap: () => context.push('/new?type=withdrawal'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _FlowButton(
                  label: 'Income',
                  amount: formatMoney(summary.earned.value, currency),
                  icon: LucideIcons.arrowDownLeft,
                  color: palette.primary,
                  tint: palette.primaryTint,
                  onTap: () => context.push('/new?type=deposit'),
                ),
              ),
            ],
          ),
        ),
        if (summary.accounts.isNotEmpty) ...[
          SectionHeader('Accounts', action: 'See all', onAction: () => context.push('/accounts')),
          SizedBox(
            height: 128,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              itemCount: summary.accounts.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) => _AccountCard(summary.accounts[index], highlighted: index == 0),
            ),
          ),
        ],
        const SizedBox(height: 16),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 20), child: _GoalCard(goals)),
        if (portfolio != null && portfolio.investments.any((investment) => investment.closedAt == null)) ...[
          const SizedBox(height: 12),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 20), child: _PortfolioCard(portfolio)),
        ],
        if (summary.topCategories.isNotEmpty) ...[
          SectionHeader('Top spending', action: 'Reports', onAction: () => context.go('/analytics')),
          for (final row in summary.topCategories.take(4))
            ListRow(
              leading: CategoryIcon(icon: row.icon, color: row.color),
              title: row.name ?? 'No category',
              subtitle: _share(row, summary.spent),
              trailing: MoneyText(row.amount.value, row.amount.currency, tone: MoneyTone.none),
            ),
        ],
        SectionHeader('Recent transactions', action: 'See all', onAction: () => context.push('/transactions')),
        switch (recent) {
          AsyncValue(hasValue: true, :final value?) when value.items.isEmpty => const EmptyState(
            icon: LucideIcons.receipt,
            title: 'Nothing here yet',
            message: 'Tap + to record your first transaction.',
          ),
          AsyncValue(hasValue: true, :final value?) => Column(children: [for (final item in value.items.take(8)) TransactionTile(item)]),
          _ => const SkeletonList(rows: 4),
        },
      ],
    );
  }

  String _share(CategoryTotal row, Amount spent) {
    final total = double.tryParse(spent.value) ?? 0;
    final value = double.tryParse(row.amount.value) ?? 0;
    return total == 0 ? 'This month' : '${(value / total * 100).round()}% of spending';
  }
}

class _FlowButton extends StatelessWidget {
  const _FlowButton({
    required this.label,
    required this.amount,
    required this.icon,
    required this.color,
    required this.tint,
    required this.onTap,
  });

  final String label;
  final String amount;
  final IconData icon;
  final Color color;
  final Color tint;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: tint,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
                child: Icon(icon, size: 18, color: Colors.white),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: color),
                    ),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(amount, style: Theme.of(context).textTheme.titleMedium),
                    ),
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

class _AccountCard extends ConsumerWidget {
  const _AccountCard(this.account, {required this.highlighted});

  final Account account;
  final bool highlighted;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final currency = account.currency ?? ref.watch(currentLedgerProvider).currency;
    final foreground = highlighted ? Colors.white : palette.foreground;
    return GestureDetector(
      onTap: () => context.push('/accounts/${account.id}', extra: account),
      child: Container(
        width: 168,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: highlighted ? palette.primary : palette.surface,
          borderRadius: BorderRadius.circular(22),
          boxShadow: highlighted
              ? [BoxShadow(color: palette.accent.withValues(alpha: 0.3), blurRadius: 18, offset: const Offset(0, 8))]
              : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(accountIcon(account), size: 22, color: highlighted ? Colors.white : palette.primary),
            const Spacer(),
            Text(
              account.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 13, color: highlighted ? Colors.white70 : palette.muted),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                formatMoney(account.balance ?? '0', currency.code, decimals: currency.decimals),
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700, letterSpacing: -0.4, color: foreground),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GoalCard extends StatelessWidget {
  const _GoalCard(this.goals);

  final List<Goal> goals;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final goal = goals.where((goal) => goal.target != null).firstOrNull;

    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      onTap: () => context.go('/goals'),
      child: Row(
        children: [
          IconBadge(LucideIcons.target, color: palette.primary, size: 46),
          const SizedBox(width: 14),
          Expanded(
            child: goal == null
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Set your goals', style: text.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                      Text('Save towards something you want', style: text.bodySmall?.copyWith(color: palette.muted)),
                    ],
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(goal.name, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                          ),
                          Text(
                            '${(goal.progress * 100).round()}%',
                            style: TextStyle(fontWeight: FontWeight.w600, color: palette.primary),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ProgressBar(value: goal.saved, max: goal.target!, color: palette.primary, height: 6),
                    ],
                  ),
          ),
          const SizedBox(width: 8),
          Icon(goal == null ? LucideIcons.plus : LucideIcons.chevronRight, size: 18, color: palette.muted),
        ],
      ),
    );
  }
}

class _PortfolioCard extends StatelessWidget {
  const _PortfolioCard(this.portfolio);

  final Portfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final gain = portfolio.gainPercent;

    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      onTap: () => context.push('/investments'),
      child: Row(
        children: [
          IconBadge(LucideIcons.trendingUp, color: palette.primary, size: 46),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Investments', style: text.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                Text(formatMoney(portfolio.value, portfolio.currency), style: text.bodySmall?.copyWith(color: palette.muted)),
              ],
            ),
          ),
          if (gain != null)
            Text(
              '${gain > 0 ? '+' : ''}${gain.toStringAsFixed(2)}%',
              style: TextStyle(fontWeight: FontWeight.w600, color: gain < 0 ? palette.negative : palette.positive),
            ),
          const SizedBox(width: 8),
          Icon(LucideIcons.chevronRight, size: 18, color: palette.muted),
        ],
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.only(top: 28),
      child: Column(
        children: [
          Skeleton(width: 90, height: 13),
          SizedBox(height: 10),
          Skeleton(width: 220, height: 40, radius: 12),
          SizedBox(height: 28),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                Expanded(child: Skeleton(height: 64, radius: 18)),
                SizedBox(width: 12),
                Expanded(child: Skeleton(height: 64, radius: 18)),
              ],
            ),
          ),
          SizedBox(height: 24),
          SkeletonList(rows: 4),
        ],
      ),
    );
  }
}

/// Says how many transactions saved offline are still waiting, or why one could not be saved.
class _OutboxBanner extends ConsumerWidget {
  const _OutboxBanner();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final outbox = ref.watch(outboxProvider).value;
    if (outbox == null || (outbox.pending.isEmpty && outbox.lastError == null)) return const SizedBox.shrink();
    final failed = outbox.pending.isEmpty;
    final count = outbox.pending.length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 10, 6, 10),
        decoration: BoxDecoration(color: failed ? palette.negativeTint : palette.primaryTint, borderRadius: BorderRadius.circular(14)),
        child: Row(
          children: [
            Icon(failed ? LucideIcons.circleAlert : LucideIcons.cloudOff, size: 18, color: failed ? palette.negative : palette.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                failed
                    ? 'An offline entry could not be saved: ${outbox.lastError}'
                    : '$count ${count == 1 ? 'transaction' : 'transactions'} saved offline, waiting to sync',
                style: TextStyle(fontSize: 13, color: failed ? palette.negative : palette.foreground),
              ),
            ),
            TextButton(
              onPressed: failed ? ref.read(outboxProvider.notifier).dismissError : ref.read(outboxProvider.notifier).flush,
              child: Text(failed ? 'Dismiss' : 'Sync now'),
            ),
          ],
        ),
      ),
    );
  }
}
