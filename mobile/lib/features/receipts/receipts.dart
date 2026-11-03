import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';

final receiptsProvider = FutureProvider.autoDispose.family<List<Attachment>, int>((ref, groupId) async {
  final ledger = ref.watch(currentLedgerProvider);
  final response = await ref
      .watch(apiProvider)
      .get(ledgerPath(ledger, 'attachments'), query: {'attachable_type': 'transaction_group', 'attachable_id': groupId});
  return (response['data'] as List).map((json) => Attachment.fromJson(json as Json)).toList();
});

final receiptImageProvider = FutureProvider.autoDispose.family<Uint8List, int>((ref, attachmentId) async {
  final ledger = ref.watch(currentLedgerProvider);
  return Uint8List.fromList(await ref.watch(apiProvider).bytes(ledgerPath(ledger, 'attachments/$attachmentId/download')));
});

Future<void> uploadReceipt(WidgetRef ref, int groupId, String filePath) async {
  final ledger = ref.read(currentLedgerProvider);
  await ref
      .read(apiProvider)
      .upload(
        ledgerPath(ledger, 'attachments'),
        filePath: filePath,
        fields: {'attachable_type': 'transaction_group', 'attachable_id': groupId},
      );
  ref.invalidate(receiptsProvider(groupId));
}

/// Asks whether to use the camera or the photo library, and returns the chosen photo.
Future<XFile?> pickReceipt(BuildContext context) async {
  final source = await showAppSheet<ImageSource>(
    context,
    title: 'Add a receipt',
    builder: (context) => Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListRow(
            leading: const IconBadge(LucideIcons.camera, color: Color(0xFF0076A8), size: 42),
            title: 'Take a photo',
            onTap: () => Navigator.pop(context, ImageSource.camera),
          ),
          ListRow(
            leading: const IconBadge(LucideIcons.image, color: Color(0xFF0076A8), size: 42),
            title: 'Choose from photos',
            onTap: () => Navigator.pop(context, ImageSource.gallery),
          ),
        ],
      ),
    ),
  );
  if (source == null) return null;
  return ImagePicker().pickImage(source: source, maxWidth: 2000, imageQuality: 80);
}

class ReceiptsSection extends ConsumerWidget {
  const ReceiptsSection({super.key, required this.groupId});

  final int groupId;

  Future<void> _add(BuildContext context, WidgetRef ref) async {
    final photo = await pickReceipt(context);
    if (photo == null || !context.mounted) return;
    try {
      await uploadReceipt(ref, groupId, photo.path);
      if (context.mounted) showToast(context, 'Receipt added');
    } catch (error) {
      if (context.mounted) showError(context, error);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final receipts = ref.watch(receiptsProvider(groupId)).value ?? const <Attachment>[];
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SectionHeader(
          'Receipts',
          action: canEdit ? 'Add' : null,
          onAction: () => _add(context, ref),
          padding: const EdgeInsets.fromLTRB(4, 8, 0, 8),
        ),
        if (receipts.isEmpty)
          SurfaceCard(
            onTap: canEdit ? () => _add(context, ref) : null,
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                Icon(LucideIcons.camera, size: 20, color: palette.muted),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(canEdit ? 'Snap or attach a receipt' : 'No receipts', style: TextStyle(color: palette.muted)),
                ),
              ],
            ),
          )
        else
          SizedBox(
            height: 96,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: receipts.length,
              separatorBuilder: (_, _) => const SizedBox(width: 10),
              itemBuilder: (context, index) => _Thumbnail(receipts[index]),
            ),
          ),
      ],
    );
  }
}

class _Thumbnail extends ConsumerWidget {
  const _Thumbnail(this.receipt);

  final Attachment receipt;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final palette = Palette.of(context);
    final image = receipt.isImage ? ref.watch(receiptImageProvider(receipt.id)).value : null;

    return GestureDetector(
      onTap: image == null
          ? null
          : () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                fullscreenDialog: true,
                builder: (_) => Scaffold(
                  backgroundColor: Colors.black,
                  appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: Text(receipt.filename)),
                  body: InteractiveViewer(child: Center(child: Image.memory(image))),
                ),
              ),
            ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 96,
          color: palette.surface,
          child: image != null
              ? Image.memory(image, fit: BoxFit.cover)
              : Center(child: Icon(receipt.isImage ? LucideIcons.image : LucideIcons.fileText, color: palette.muted)),
        ),
      ),
    );
  }
}
