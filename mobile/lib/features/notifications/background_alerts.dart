import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:go_router/go_router.dart';
import 'package:workmanager/workmanager.dart';

import '../../api/api_client.dart';
import '../../api/models.dart';
import '../auth/session.dart';

/// Also listed under BGTaskSchedulerPermittedIdentifiers in the iOS Info.plist.
const alertsTask = 'com.nayeemdev.ledgerfly.alerts';
const _enabledKey = 'background_alerts';
const _seenKey = 'background_alerts_seen';

final _notifications = FlutterLocalNotificationsPlugin();
VoidCallback? _onTap;
Future<bool?>? _initialized;

/// Initializes the plugin once per isolate; initializing again would drop the tap handler.
Future<bool?> _initialize() =>
    _initialized ??= _notifications.initialize(settings: _settings, onDidReceiveNotificationResponse: (_) => _onTap?.call());

const _settings = InitializationSettings(
  android: AndroidInitializationSettings('ic_notification'),
  iOS: DarwinInitializationSettings(requestAlertPermission: false, requestBadgePermission: false, requestSoundPermission: false),
);

const _details = NotificationDetails(
  android: AndroidNotificationDetails(
    'alerts',
    'Alerts',
    channelDescription: 'Budget, bill and balance alerts',
    importance: Importance.high,
    priority: Priority.high,
  ),
  iOS: DarwinNotificationDetails(),
);

@pragma('vm:entry-point')
void alertsDispatcher() {
  Workmanager().executeTask((task, input) async {
    try {
      await checkAlerts();
    } on Object {
      // Offline or signed out: the next scheduled run tries again.
    }
    return true;
  });
}

/// Shows a notification for every unread inbox item that arrived since the last check.
Future<void> checkAlerts() async {
  const storage = FlutterSecureStorage();
  final session = await readSession(storage);
  if (session == null || await storage.read(key: _enabledKey) != 'true') return;

  final response = await ApiClient(
    baseUrl: session.serverUrl,
    token: session.token,
  ).get('notifications', query: {'unread': 1, 'per_page': 10});
  // Items are not marked seen during quiet hours, so they still arrive once the quiet hours end.
  if (response['meta']['quiet'] == true) return;

  final seen = DateTime.tryParse(await storage.read(key: _seenKey) ?? '');
  final fresh = (response['data'] as List)
      .map((json) => Notice.fromJson(json as Json))
      .where((notice) => seen == null || notice.createdAt.isAfter(seen))
      .toList();
  if (fresh.isEmpty) return;

  await _initialize();
  for (final notice in fresh.reversed) {
    await _notifications.show(id: notice.id.hashCode, title: notice.title, body: notice.body, notificationDetails: _details);
  }
  await storage.write(key: _seenKey, value: fresh.first.createdAt.toIso8601String());
}

final backgroundAlertsProvider = AsyncNotifierProvider<BackgroundAlertsNotifier, bool>(BackgroundAlertsNotifier.new);

class BackgroundAlertsNotifier extends AsyncNotifier<bool> {
  @override
  Future<bool> build() async => await ref.read(storageProvider).read(key: _enabledKey) == 'true';

  /// Returns false when the phone refuses notification permission, leaving alerts off.
  Future<bool> set(bool enabled) async {
    final storage = ref.read(storageProvider);
    if (enabled) {
      if (!await _requestPermission()) return false;
      await storage.write(key: _seenKey, value: DateTime.now().toUtc().toIso8601String());
      await Workmanager().registerPeriodicTask(
        alertsTask,
        alertsTask,
        frequency: const Duration(minutes: 15),
        constraints: Constraints(networkType: NetworkType.connected),
        existingWorkPolicy: ExistingPeriodicWorkPolicy.update,
      );
    } else {
      await Workmanager().cancelByUniqueName(alertsTask);
    }
    await storage.write(key: _enabledKey, value: '$enabled');
    state = AsyncData(enabled);
    return true;
  }
}

Future<bool> _requestPermission() async {
  await _initialize();
  final ios = _notifications.resolvePlatformSpecificImplementation<IOSFlutterLocalNotificationsPlugin>();
  final android = _notifications.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
  return await ios?.requestPermissions(alert: true, sound: true) ?? await android?.requestNotificationsPermission() ?? false;
}

/// Opens the inbox when an alert is tapped, both while the app runs and when the tap launched it.
class AlertTaps extends StatefulWidget {
  const AlertTaps({super.key, required this.child});

  final Widget child;

  @override
  State<AlertTaps> createState() => _AlertTapsState();
}

class _AlertTapsState extends State<AlertTaps> {
  static bool _launchHandled = false;

  void _open() {
    if (mounted) context.push('/notifications');
  }

  Future<void> _listen() async {
    _onTap = _open;
    await _initialize();
    final launch = await _notifications.getNotificationAppLaunchDetails();
    if (!_launchHandled && (launch?.didNotificationLaunchApp ?? false)) {
      _launchHandled = true;
      _open();
    }
  }

  @override
  void initState() {
    super.initState();
    _listen();
  }

  @override
  void dispose() {
    if (_onTap == _open) _onTap = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
