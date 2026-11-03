import 'dart:convert';
import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import '../../api/api_client.dart';
import '../../api/models.dart';
import '../auth/session.dart';
import '../ledger/ledger_data.dart';

/// A transaction saved while offline, waiting to be sent.
class Pending {
  const Pending({required this.ledgerId, required this.body, this.receipt});

  factory Pending.fromJson(Json json) =>
      Pending(ledgerId: json['ledger_id'] as int, body: (json['body'] as Map).cast<String, dynamic>(), receipt: json['receipt'] as String?);

  final int ledgerId;
  final Json body;

  /// A copy of the receipt photo kept in app storage until it is uploaded.
  final String? receipt;

  Json toJson() => {'ledger_id': ledgerId, 'body': body, 'receipt': receipt};
}

typedef OutboxState = ({List<Pending> pending, String? lastError});

final outboxProvider = AsyncNotifierProvider<OutboxNotifier, OutboxState>(OutboxNotifier.new);

class OutboxNotifier extends AsyncNotifier<OutboxState> {
  bool _flushing = false;

  Future<File> _file() async => File('${(await getApplicationSupportDirectory()).path}/outbox.json');

  @override
  Future<OutboxState> build() async {
    final file = await _file();
    if (!await file.exists()) return (pending: <Pending>[], lastError: null);
    final raw = jsonDecode(await file.readAsString()) as List;
    return (pending: raw.map((item) => Pending.fromJson((item as Map).cast<String, dynamic>())).toList(), lastError: null);
  }

  Future<void> _save(List<Pending> pending, {String? error}) async {
    await (await _file()).writeAsString(jsonEncode([for (final item in pending) item.toJson()]));
    state = AsyncData((pending: pending, lastError: error));
  }

  Future<void> add(int ledgerId, Json body, {String? receiptPath}) async {
    String? receipt;
    if (receiptPath != null) {
      final dir = Directory('${(await getApplicationSupportDirectory()).path}/outbox');
      await dir.create(recursive: true);
      receipt = (await File(receiptPath).copy('${dir.path}/${DateTime.now().microsecondsSinceEpoch}.jpg')).path;
    }
    final current = (await future).pending;
    await _save([...current, Pending(ledgerId: ledgerId, body: body, receipt: receipt)]);
  }

  void dismissError() => state = AsyncData((pending: state.value?.pending ?? const [], lastError: null));

  /// Sends queued transactions in order, stopping at the first one that cannot reach the server.
  Future<void> flush() async {
    if (_flushing || ref.read(sessionProvider).value == null) return;
    _flushing = true;
    try {
      final api = ref.read(apiProvider);
      var pending = [...(await future).pending];
      String? error;
      var sent = false;
      while (pending.isNotEmpty) {
        final item = pending.first;
        try {
          final response = await api.post('ledgers/${item.ledgerId}/transactions', item.body);
          if (item.receipt != null) {
            final groupId = (response['data'] as Json)['id'] as int;
            await api.upload(
              'ledgers/${item.ledgerId}/attachments',
              filePath: item.receipt!,
              fields: {'attachable_type': 'transaction_group', 'attachable_id': groupId},
            );
          }
          sent = true;
        } on ApiException catch (failure) {
          if (failure.status == null) break;
          // The server rejected it, so retrying will not help. Drop it and say why.
          error = failure.message;
        }
        if (item.receipt != null) await File(item.receipt!).delete().catchError((_) => File(''));
        pending = pending.sublist(1);
        await _save(pending, error: error);
      }
      if (sent) refreshLedgerData(ref.invalidate);
    } finally {
      _flushing = false;
    }
  }
}

/// Sends anything queued when the signed in app starts and whenever it comes back to the foreground.
class OutboxSync extends ConsumerStatefulWidget {
  const OutboxSync({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<OutboxSync> createState() => _OutboxSyncState();
}

class _OutboxSyncState extends ConsumerState<OutboxSync> {
  late final AppLifecycleListener _listener;

  void _flush() => ref.read(outboxProvider.notifier).flush();

  @override
  void initState() {
    super.initState();
    _listener = AppLifecycleListener(onResume: _flush);
    WidgetsBinding.instance.addPostFrameCallback((_) => _flush());
  }

  @override
  void dispose() {
    _listener.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
