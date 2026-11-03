import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../app/theme.dart';
import '../../shared/widgets.dart';
import 'session.dart';

const _lockKey = 'biometric_lock';

final lockEnabledProvider = AsyncNotifierProvider<LockEnabledNotifier, bool>(LockEnabledNotifier.new);

class LockEnabledNotifier extends AsyncNotifier<bool> {
  @override
  Future<bool> build() async => await ref.read(storageProvider).read(key: _lockKey) == 'true';

  /// Turning the lock on asks for Face ID first, so nobody enables a lock they cannot open.
  Future<bool> set(bool enabled) async {
    if (enabled && !await authenticate('Confirm it is you to turn on the lock')) return false;
    await ref.read(storageProvider).write(key: _lockKey, value: '$enabled');
    state = AsyncData(enabled);
    return true;
  }
}

Future<bool> authenticate(String reason) async {
  final auth = LocalAuthentication();
  if (!await auth.isDeviceSupported()) return false;
  try {
    return await auth.authenticate(localizedReason: reason, persistAcrossBackgrounding: true);
  } on LocalAuthException {
    return false;
  }
}

class LockGate extends ConsumerStatefulWidget {
  const LockGate({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<LockGate> createState() => _LockGateState();
}

class _LockGateState extends ConsumerState<LockGate> with WidgetsBindingObserver {
  bool _locked = true;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.hidden && !_checking) setState(() => _locked = true);
    if (state == AppLifecycleState.resumed) _unlock();
  }

  Future<void> _unlock() async {
    if (_checking || !_locked || !_active) return;
    _checking = true;
    final ok = await authenticate('Unlock LedgerFly');
    _checking = false;
    if (ok && mounted) setState(() => _locked = false);
  }

  bool get _active => (ref.read(lockEnabledProvider).value ?? false) && ref.read(sessionProvider).value != null;

  @override
  Widget build(BuildContext context) {
    final enabled = ref.watch(lockEnabledProvider).value ?? false;
    final signedIn = ref.watch(sessionProvider).value != null;
    if (!enabled || !signedIn || !_locked) return widget.child;

    WidgetsBinding.instance.addPostFrameCallback((_) => _unlock());
    return Stack(
      children: [
        widget.child,
        Positioned.fill(
          child: Material(
            color: Theme.of(context).scaffoldBackgroundColor,
            child: SafeArea(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const LogoMark(size: 56),
                  const SizedBox(height: 16),
                  Text('LedgerFly is locked', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 4),
                  Text('Unlock to see your finances.', style: TextStyle(color: Palette.of(context).muted)),
                  const SizedBox(height: 24),
                  FilledButton.icon(onPressed: _unlock, icon: const Icon(LucideIcons.scanFace, size: 18), label: const Text('Unlock')),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
