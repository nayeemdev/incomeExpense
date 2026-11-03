import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/widgets.dart';
import '../ledger/ledger_data.dart';
import 'transaction_tile.dart';

class TransactionList extends ConsumerWidget {
  const TransactionList({super.key, required this.filter, this.header});

  final TransactionFilter filter;
  final Widget? header;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final provider = transactionsProvider(filter);
    final feed = ref.watch(provider);

    return RefreshIndicator(
      onRefresh: () => ref.refresh(provider.future),
      child: AsyncView(
        value: feed,
        onRetry: () => ref.invalidate(provider),
        builder: (feed) {
          final rows = groupedByDay(feed.items);
          return NotificationListener<ScrollNotification>(
            onNotification: (notification) {
              if (notification.metrics.extentAfter < 400) ref.read(provider.notifier).loadMore();
              return false;
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(bottom: 96),
              children: [
                ?header,
                if (feed.items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 48),
                    child: MessageView(
                      icon: Icons.receipt_long_outlined,
                      title: 'No transactions',
                      message: 'Nothing matches yet. Tap + to add one.',
                    ),
                  ),
                ...rows,
                if (feed.loadingMore) const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
              ],
            ),
          );
        },
      ),
    );
  }
}
