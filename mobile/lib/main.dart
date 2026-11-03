import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:workmanager/workmanager.dart';

import 'app/preferences.dart';
import 'app/router.dart';
import 'app/theme.dart';
import 'features/auth/lock.dart';
import 'features/auth/session.dart';
import 'features/notifications/background_alerts.dart';
import 'shared/widgets.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  Workmanager().initialize(alertsDispatcher);
  runApp(const ProviderScope(child: LedgerFlyApp()));
}

class LedgerFlyApp extends ConsumerWidget {
  const LedgerFlyApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final light = buildTheme(Brightness.light);
    final dark = buildTheme(Brightness.dark);
    final mode = ref.watch(themeModeProvider).value ?? ThemeMode.system;

    if (!session.hasValue) {
      return MaterialApp(
        theme: light,
        darkTheme: dark,
        themeMode: mode,
        debugShowCheckedModeBanner: false,
        home: const Scaffold(body: Center(child: LogoMark(size: 48))),
      );
    }

    return MaterialApp.router(
      title: 'LedgerFly',
      theme: light,
      darkTheme: dark,
      themeMode: mode,
      debugShowCheckedModeBanner: false,
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => LockGate(child: child!),
    );
  }
}
