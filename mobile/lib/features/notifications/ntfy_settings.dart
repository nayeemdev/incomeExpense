import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';

typedef NtfySettings = ({String? server, String? topic, bool hasToken, bool private, String defaultServer});

final ntfySettingsProvider = FutureProvider.autoDispose<NtfySettings>((ref) async {
  final data = (await ref.watch(apiProvider).get('notification-settings'))['data'] as Json;
  final ntfy = data['ntfy'] as Json?;
  return (
    server: ntfy?['server'] as String?,
    topic: ntfy?['topic'] as String?,
    hasToken: ntfy?['has_token'] as bool? ?? false,
    private: ntfy?['private'] as bool? ?? true,
    defaultServer: data['ntfy_default_server'] as String? ?? 'https://ntfy.sh',
  );
});

/// A topic name long and random enough that nobody can guess it on a public ntfy server.
String randomTopic() {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  final random = Random.secure();
  return 'ledgerfly-${List.generate(9, (_) => chars[random.nextInt(chars.length)]).join()}';
}

class NtfySheet extends ConsumerStatefulWidget {
  const NtfySheet({super.key, required this.settings});

  final NtfySettings settings;

  @override
  ConsumerState<NtfySheet> createState() => _NtfySheetState();
}

class _NtfySheetState extends ConsumerState<NtfySheet> {
  late final _server = TextEditingController(text: widget.settings.server ?? widget.settings.defaultServer);
  late final _topic = TextEditingController(text: widget.settings.topic ?? '');
  final _token = TextEditingController();
  late bool _private = widget.settings.private;
  bool _busy = false;

  bool get _saved => widget.settings.topic != null;

  @override
  void dispose() {
    _server.dispose();
    _topic.dispose();
    _token.dispose();
    super.dispose();
  }

  Future<void> _run(Future<void> Function() action, String done) async {
    setState(() => _busy = true);
    try {
      await action();
      ref.invalidate(ntfySettingsProvider);
      if (mounted) {
        showToast(context, done);
        Navigator.pop(context);
      }
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save() => _run(
    () => ref.read(apiProvider).patch('notification-settings', {
      'ntfy': {
        'server': _server.text.trim(),
        'topic': _topic.text.trim(),
        'private': _private,
        if (_token.text.isNotEmpty) 'token': _token.text,
      },
    }),
    'ntfy settings saved',
  );

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final ready = _server.text.trim().isNotEmpty && _topic.text.trim().isNotEmpty && !_busy;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Install the free ntfy app and subscribe to this server and topic. LedgerFly alerts then arrive there instantly.',
            style: TextStyle(color: palette.muted, height: 1.4),
          ),
          const SizedBox(height: 18),
          const FieldLabel('Server', required: true),
          TextField(controller: _server, keyboardType: TextInputType.url, autocorrect: false, onChanged: (_) => setState(() {})),
          const SizedBox(height: 14),
          const FieldLabel('Topic', required: true),
          TextField(
            controller: _topic,
            autocorrect: false,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: 'ledgerfly-...',
              suffixIcon: TextButton.icon(
                onPressed: () => setState(() => _topic.text = randomTopic()),
                icon: const Icon(LucideIcons.shuffle, size: 16),
                label: const Text('Generate'),
              ),
            ),
          ),
          const SizedBox(height: 14),
          const FieldLabel('Access token'),
          TextField(
            controller: _token,
            obscureText: true,
            decoration: InputDecoration(
              hintText: widget.settings.hasToken ? 'Saved. Type a new one to replace it.' : 'Only for protected topics',
            ),
          ),
          const SizedBox(height: 10),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            value: _private,
            activeTrackColor: palette.primary,
            onChanged: (value) => setState(() => _private = value),
            title: const Text('Hide amounts and details'),
            subtitle: Text('Alerts say what happened, and you open LedgerFly for the rest.', style: TextStyle(color: palette.muted)),
          ),
          const SizedBox(height: 14),
          FilledButton(onPressed: ready ? _save : null, child: const Text('Save')),
          if (_saved) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () => _run(() => ref.read(apiProvider).post('notification-settings/ntfy-test'), 'Test sent. Check the ntfy app.'),
              icon: const Icon(LucideIcons.send, size: 18),
              label: const Text('Send test'),
            ),
            TextButton(
              onPressed: _busy
                  ? null
                  : () => _run(() => ref.read(apiProvider).patch('notification-settings', {'ntfy': null}), 'ntfy is turned off'),
              child: Text('Turn off', style: TextStyle(color: palette.negative)),
            ),
          ],
        ],
      ),
    );
  }
}
