import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';
import 'transaction_tile.dart';

/// A scrolling list of transactions grouped by day, with `header` widgets above it.
class TransactionList extends ConsumerWidget {
  const TransactionList({super.key, required this.filter, this.header = const []});

  final TransactionFilter filter;
  final List<Widget> header;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final provider = transactionsProvider(filter);
    final feed = ref.watch(provider);

    return RefreshIndicator.adaptive(
      onRefresh: () => ref.refresh(provider.future),
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.extentAfter < 400) ref.read(provider.notifier).loadMore();
          return false;
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 40),
          children: [
            ...header,
            ...switch (feed) {
              AsyncValue(hasValue: true, :final value?) when value.items.isEmpty => [
                const EmptyState(icon: LucideIcons.receipt, title: 'No transactions', message: 'Nothing matches yet. Tap + to add one.'),
              ],
              AsyncValue(hasValue: true, :final value?) => [
                ...groupedByDay(context, value.items),
                if (value.loadingMore)
                  const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: CircularProgressIndicator.adaptive()),
                  ),
              ],
              AsyncValue(:final error?) => [
                AsyncView(
                  value: AsyncError<void>(error, StackTrace.empty),
                  onRetry: () => ref.invalidate(provider),
                  builder: (_) => const SizedBox(),
                ),
              ],
              _ => [const SkeletonList(rows: 6)],
            },
          ],
        ),
      ),
    );
  }
}
