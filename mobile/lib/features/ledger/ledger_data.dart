import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show ProviderOrFamily;

import '../../api/models.dart';
import '../../shared/format.dart';
import '../auth/session.dart';
import 'ledger.dart';

const _all = 200;

List<T> _data<T>(dynamic response, T Function(Json) parse) => (response['data'] as List).map((json) => parse(json as Json)).toList();

final summaryProvider = FutureProvider.autoDispose<Summary>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final (start, end) = Dates.thisMonth();
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'summary'), query: {'start': isoDate(start), 'end': isoDate(end)});
  return Summary.fromJson(response['data'] as Json);
});

/// Keyed by a comma separated list of account types, so equal requests share one cache entry.
final accountsProvider = FutureProvider.autoDispose.family<List<Account>, String>((ref, types) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'accounts'), query: {'type': types.split(','), 'per_page': _all});
  return _data(response, Account.fromJson);
});

final accountProvider = FutureProvider.autoDispose.family<Account, String>((ref, id) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'accounts/$id'));
  return Account.fromJson(response['data'] as Json);
});

final categoriesProvider = FutureProvider.autoDispose<List<NamedRef>>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'categories'), query: {'per_page': _all});
  return _data(response, NamedRef.fromJson);
});

final budgetsProvider = FutureProvider.autoDispose<List<Budget>>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'budgets'), query: {'active': 1});
  return _data(response, Budget.fromJson);
});

final billsProvider = FutureProvider.autoDispose<List<Bill>>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'bills'), query: {'active': 1});
  return _data(response, Bill.fromJson);
});

typedef FrequentPicks = ({List<Transaction> transactions, List<NamedRef> categories, List<AccountRef> counterparties});

final frequentProvider = FutureProvider.autoDispose.family<FrequentPicks, String>((ref, type) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'autocomplete/frequent'), query: {'type': type});
  final data = response['data'] as Json;
  List<T> list<T>(String key, T Function(Json) parse) => (data[key] as List? ?? const []).map((json) => parse(json as Json)).toList();
  return (
    transactions: list('transactions', Transaction.fromJson),
    categories: list('categories', NamedRef.fromJson),
    counterparties: list('counterparties', AccountRef.fromJson),
  );
});

typedef TransactionFilter = ({String query, String? type, String? accountId});

class TransactionFeed {
  const TransactionFeed(this.items, this.page, this.lastPage, {this.loadingMore = false});

  final List<Transaction> items;
  final int page;
  final int lastPage;
  final bool loadingMore;

  bool get hasMore => page < lastPage;
}

final transactionsProvider = AsyncNotifierProvider.autoDispose.family<TransactionsNotifier, TransactionFeed, TransactionFilter>(
  TransactionsNotifier.new,
);

class TransactionsNotifier extends AsyncNotifier<TransactionFeed> {
  TransactionsNotifier(this.filter);

  final TransactionFilter filter;

  @override
  Future<TransactionFeed> build() => _fetch(1, const []);

  Future<void> loadMore() async {
    final feed = state.value;
    if (feed == null || !feed.hasMore || feed.loadingMore) return;
    state = AsyncData(TransactionFeed(feed.items, feed.page, feed.lastPage, loadingMore: true));
    state = await AsyncValue.guard(() => _fetch(feed.page + 1, feed.items));
  }

  Future<TransactionFeed> _fetch(int page, List<Transaction> previous) async {
    final ledger = ref.read(currentLedgerProvider);
    final response = await ref
        .read(apiProvider)
        .get(
          ledgerPath(ledger, 'transactions'),
          query: {
            'page': page,
            'query': filter.query,
            'type': filter.type == null ? null : [filter.type],
            'account_id': filter.accountId,
          },
        );
    final meta = response['meta'] as Json;
    return TransactionFeed([...previous, ..._data(response, Transaction.fromJson)], page, meta['last_page'] as int);
  }
}

final transactionGroupProvider = FutureProvider.autoDispose.family<TransactionGroup, String>((ref, id) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'transactions/$id'));
  return TransactionGroup.fromJson(response['data'] as Json);
});

/// Everything that changes when a transaction is added, edited or removed. Pass `ref.invalidate`.
void refreshLedgerData(void Function(ProviderOrFamily provider) invalidate) {
  invalidate(summaryProvider);
  invalidate(accountsProvider);
  invalidate(accountProvider);
  invalidate(budgetsProvider);
  invalidate(billsProvider);
  invalidate(transactionsProvider);
  invalidate(frequentProvider);
  invalidate(reportProvider);
  invalidate(goalsProvider);
  invalidate(portfolioProvider);
  invalidate(investmentProvider);
  invalidate(categoriesProvider);
  invalidate(transactionGroupProvider);
}

enum ReportRange { week, month, year }

final reportProvider = FutureProvider.autoDispose.family<Report, ReportRange>((ref, range) async {
  final ledger = ref.watch(currentLedgerProvider);
  final today = Dates.today();
  final (start, end, interval) = switch (range) {
    ReportRange.week => (today.subtract(const Duration(days: 6)), today, 'day'),
    ReportRange.month => (DateTime(today.year, today.month), DateTime(today.year, today.month + 1, 0), 'day'),
    ReportRange.year => (DateTime(today.year), DateTime(today.year, 12, 31), 'month'),
  };
  final response = await ref
      .watch(apiProvider)
      .get(ledgerPath(ledger, 'reports/overview'), query: {'start': isoDate(start), 'end': isoDate(end), 'interval': interval});
  return Report.fromJson(response as Json);
});

final goalsProvider = FutureProvider.autoDispose<List<Goal>>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'piggy-banks'));
  return _data(response, Goal.fromJson);
});

final portfolioProvider = FutureProvider.autoDispose<Portfolio>((ref) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'investments'));
  return Portfolio.fromJson(response as Json);
});

final investmentProvider = FutureProvider.autoDispose.family<Investment, String>((ref, id) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref.watch(apiProvider).get(ledgerPath(ledger, 'investments/$id'));
  return Investment.fromJson(response['data'] as Json);
});

final userProvider = FutureProvider<User>((ref) async {
  final response = await ref.watch(apiProvider).get('user');
  return User.fromJson(response['data'] as Json);
});

final categoryStylesProvider = FutureProvider<({List<String> icons, List<String> colors})>((ref) async {
  final response = await ref.watch(apiProvider).get('category-styles');
  final data = response['data'] as Json;
  return (icons: (data['icons'] as List).cast<String>(), colors: (data['colors'] as List).cast<String>());
});

/// Category style by id, so report rows that only carry an id can still show their icon.
final categoryStyleById = Provider.autoDispose<Map<String, NamedRef>>((ref) {
  final categories = ref.watch(categoriesProvider).value ?? const <NamedRef>[];
  return {for (final category in categories) category.id: category};
});
