import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';

final notificationsProvider = FutureProvider.autoDispose<List<Notice>>((ref) async {
  final response = await ref.watch(apiProvider).get('notifications', query: {'per_page': 50});
  return (response['data'] as List).map((json) => Notice.fromJson(json as Json)).toList();
});

final unreadCountProvider = FutureProvider.autoDispose<int>((ref) async {
  final response = await ref.watch(apiProvider).get('notifications', query: {'unread': 1, 'per_page': 1});
  return response['meta']['unread'] as int;
});

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  Future<void> _run(BuildContext context, WidgetRef ref, Future<void> Function() action) async {
    try {
      await action();
      ref
        ..invalidate(notificationsProvider)
        ..invalidate(unreadCountProvider);
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.watch(apiProvider);
    final notices = ref.watch(notificationsProvider);
    final hasUnread = notices.value?.any((notice) => notice.readAt == null) ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (hasUnread)
            TextButton(onPressed: () => _run(context, ref, () => api.post('notifications/read-all')), child: const Text('Mark all read')),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(notificationsProvider.future),
        child: AsyncView(
          value: notices,
          onRetry: () => ref.invalidate(notificationsProvider),
          builder: (notices) => notices.isEmpty
              ? ListView(children: const [
                  MessageView(
                    icon: Icons.notifications_none,
                    title: 'You are all caught up',
                    message: 'Bill reminders, budget alerts and other updates show up here.',
                  ),
                ])
              : ListView.separated(
                  itemCount: notices.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final notice = notices[index];
                    return Dismissible(
                      key: ValueKey(notice.id),
                      direction: DismissDirection.endToStart,
                      background: Container(
                        color: Palette.of(context).expense,
                        alignment: Alignment.centerRight,
                        padding: const EdgeInsets.only(right: 20),
                        child: const Icon(Icons.delete_outline, color: Colors.white),
                      ),
                      onDismissed: (_) => _run(context, ref, () => api.delete('notifications/${notice.id}')),
                      child: ListTile(
                        leading: Icon(
                          notice.readAt == null ? Icons.circle : Icons.circle_outlined,
                          size: 10,
                          color: notice.readAt == null ? Theme.of(context).colorScheme.primary : Palette.of(context).border,
                        ),
                        minLeadingWidth: 10,
                        title: Text(notice.title, style: TextStyle(fontWeight: notice.readAt == null ? FontWeight.w600 : null)),
                        subtitle: Text('${notice.body}\n${formatDay(notice.createdAt)}'),
                        isThreeLine: true,
                        onTap: notice.readAt == null ? () => _run(context, ref, () => api.post('notifications/${notice.id}/read')) : null,
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
