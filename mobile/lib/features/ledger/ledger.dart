import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/models.dart';
import '../auth/session.dart';

const _ledgerKey = 'ledger_id';

final ledgersProvider = FutureProvider<List<Ledger>>((ref) async {
  final response = await ref.watch(apiProvider).get('ledgers');
  return (response['data'] as List).map((json) => Ledger.fromJson(json as Json)).toList();
});

final ledgerProvider = AsyncNotifierProvider<LedgerNotifier, Ledger?>(LedgerNotifier.new);

class LedgerNotifier extends AsyncNotifier<Ledger?> {
  @override
  Future<Ledger?> build() async {
    final ledgers = await ref.watch(ledgersProvider.future);
    if (ledgers.isEmpty) return null;
    final saved = int.tryParse(await ref.read(storageProvider).read(key: _ledgerKey) ?? '');
    return ledgers.firstWhere((ledger) => ledger.id == saved, orElse: () => ledgers.first);
  }

  Future<void> select(Ledger ledger) async {
    await ref.read(storageProvider).write(key: _ledgerKey, value: '${ledger.id}');
    state = AsyncData(ledger);
  }
}

/// The selected ledger. Only read this below the shell, which waits for it to load.
final currentLedgerProvider = Provider<Ledger>((ref) {
  final ledger = ref.watch(ledgerProvider).value;
  if (ledger == null) throw StateError('No ledger selected');
  return ledger;
});

String ledgerPath(Ledger ledger, String path) => 'ledgers/${ledger.id}/$path';
