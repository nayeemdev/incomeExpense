import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/router.dart';
import 'app/theme.dart';
import 'features/auth/lock.dart';
import 'features/auth/session.dart';

void main() => runApp(const ProviderScope(child: LedgerFlyApp()));

class LedgerFlyApp extends ConsumerWidget {
  const LedgerFlyApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final light = buildTheme(Brightness.light);
    final dark = buildTheme(Brightness.dark);

    if (!session.hasValue) {
      return MaterialApp(
        theme: light,
        darkTheme: dark,
        debugShowCheckedModeBanner: false,
        home: const Scaffold(body: Center(child: CircularProgressIndicator())),
      );
    }

    return MaterialApp.router(
      title: 'LedgerFly',
      theme: light,
      darkTheme: dark,
      debugShowCheckedModeBanner: false,
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => LockGate(child: child!),
    );
  }
}
