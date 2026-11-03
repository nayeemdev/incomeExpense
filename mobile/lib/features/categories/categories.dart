import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/category_style.dart';
import '../../shared/widgets.dart';
import '../auth/session.dart';
import '../ledger/ledger.dart';
import '../ledger/ledger_data.dart';

/// Opens the category picker and returns the chosen category, or null to clear it.
Future<NamedRef?> pickCategory(BuildContext context, {NamedRef? selected}) {
  return showAppSheet<NamedRef?>(
    context,
    title: 'Select category',
    builder: (_) => _CategoryPicker(selected: selected),
  );
}

class _CategoryPicker extends ConsumerStatefulWidget {
  const _CategoryPicker({this.selected});

  final NamedRef? selected;

  @override
  ConsumerState<_CategoryPicker> createState() => _CategoryPickerState();
}

class _CategoryPickerState extends ConsumerState<_CategoryPicker> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final categories = ref.watch(categoriesProvider);
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: TextField(
            onChanged: (value) => setState(() => _query = value.trim().toLowerCase()),
            decoration: const InputDecoration(hintText: 'Search categories', prefixIcon: Icon(LucideIcons.search, size: 18)),
          ),
        ),
        const SizedBox(height: 12),
        Flexible(
          child: AsyncView(
            value: categories,
            builder: (categories) {
              final visible = categories.where((category) => category.name.toLowerCase().contains(_query)).toList();
              return GridView.builder(
                shrinkWrap: true,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 4,
                  mainAxisSpacing: 14,
                  childAspectRatio: 0.92,
                ),
                itemCount: visible.length + (canEdit ? 1 : 0),
                itemBuilder: (context, index) {
                  if (index == visible.length) {
                    return _Choice(
                      tile: Container(
                        width: 54,
                        height: 54,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: palette.input, width: 1.5),
                        ),
                        child: Icon(LucideIcons.plus, color: palette.muted),
                      ),
                      label: 'New',
                      onTap: () async {
                        final created = await showCategoryEditor(context, ref, name: _query);
                        if (created != null && context.mounted) Navigator.pop(context, created);
                      },
                    );
                  }
                  final category = visible[index];
                  final selected = widget.selected?.id == category.id;
                  return _Choice(
                    tile: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        CategoryIcon(icon: category.icon, color: category.color, size: 54),
                        if (selected)
                          Positioned(
                            right: -4,
                            top: -4,
                            child: Container(
                              decoration: BoxDecoration(
                                color: palette.primary,
                                shape: BoxShape.circle,
                                border: Border.all(color: palette.card, width: 2),
                              ),
                              padding: const EdgeInsets.all(2),
                              child: const Icon(LucideIcons.check, size: 12, color: Colors.white),
                            ),
                          ),
                      ],
                    ),
                    label: category.name,
                    onTap: () => Navigator.pop(context, category),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }
}

class _Choice extends StatelessWidget {
  const _Choice({required this.tile, required this.label, required this.onTap});

  final Widget tile;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Column(
        children: [
          tile,
          const SizedBox(height: 6),
          Text(
            label,
            maxLines: 2,
            textAlign: TextAlign.center,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 12, height: 1.2),
          ),
        ],
      ),
    );
  }
}

/// Creates a category, or edits [category] when given. Returns the saved category.
Future<NamedRef?> showCategoryEditor(BuildContext context, WidgetRef ref, {NamedRef? category, String name = ''}) async {
  final saved = await showAppSheet<NamedRef>(
    context,
    title: category == null ? 'Add new category' : 'Edit category',
    builder: (_) => _CategoryEditor(category: category, initialName: name),
  );
  if (saved != null && category == null && context.mounted) {
    await showSuccess(context, title: 'Category added', message: '${saved.name} is ready to use for your transactions.');
  }
  return saved;
}

class _CategoryEditor extends ConsumerStatefulWidget {
  const _CategoryEditor({this.category, this.initialName = ''});

  final NamedRef? category;
  final String initialName;

  @override
  ConsumerState<_CategoryEditor> createState() => _CategoryEditorState();
}

class _CategoryEditorState extends ConsumerState<_CategoryEditor> {
  late final _name = TextEditingController(text: widget.category?.name ?? _capitalise(widget.initialName));
  late String? _icon = widget.category?.icon;
  late String? _color = widget.category?.color;
  bool _saving = false;

  static String _capitalise(String value) => value.isEmpty ? value : value[0].toUpperCase() + value.substring(1);

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final ledger = ref.read(currentLedgerProvider);
    final api = ref.read(apiProvider);
    final body = {'name': _name.text.trim(), 'icon': _icon, 'color': _color};
    setState(() => _saving = true);
    try {
      final response = widget.category == null
          ? await api.post(ledgerPath(ledger, 'categories'), body)
          : await api.patch(ledgerPath(ledger, 'categories/${widget.category!.id}'), body);
      ref
        ..invalidate(categoriesProvider)
        ..invalidate(transactionsProvider)
        ..invalidate(summaryProvider);
      if (mounted) Navigator.pop(context, NamedRef.fromJson(response['data'] as Json));
    } catch (error) {
      if (mounted) showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final styles = ref.watch(categoryStylesProvider).value;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: palette.accentTint, borderRadius: BorderRadius.circular(28)),
              child: CategoryIcon(icon: _icon ?? 'tag', color: _color ?? 'sky', size: 72),
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _name,
            autofocus: widget.category == null,
            textCapitalization: TextCapitalization.sentences,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(hintText: 'Category name'),
          ),
          if (styles != null) ...[
            const SizedBox(height: 18),
            const FieldLabel('Color'),
            SizedBox(
              height: 36,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: styles.colors.length,
                separatorBuilder: (_, _) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final name = styles.colors[index];
                  final selected = (_color ?? 'sky') == name;
                  return GestureDetector(
                    onTap: () => setState(() => _color = name),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      width: 36,
                      decoration: BoxDecoration(
                        color: categoryColor(name),
                        shape: BoxShape.circle,
                        border: Border.all(color: selected ? palette.foreground : Colors.transparent, width: 2.5),
                      ),
                      child: selected ? const Icon(LucideIcons.check, size: 16, color: Colors.white) : null,
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 18),
            const FieldLabel('Icon'),
            GridView.count(
              crossAxisCount: 6,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              children: [
                for (final name in styles.icons)
                  GestureDetector(
                    onTap: () => setState(() => _icon = name),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      decoration: BoxDecoration(
                        color: _icon == name ? categoryColor(_color ?? 'sky').withValues(alpha: 0.15) : palette.surface,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: _icon == name ? categoryColor(_color ?? 'sky') : Colors.transparent, width: 1.5),
                      ),
                      child: Icon(categoryIcon(name), size: 20, color: _icon == name ? categoryColor(_color ?? 'sky') : palette.muted),
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _name.text.trim().isEmpty || _saving ? null : _save,
            child: Text(widget.category == null ? 'Add category' : 'Save changes'),
          ),
        ],
      ),
    );
  }
}

class CategoriesScreen extends ConsumerWidget {
  const CategoriesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categories = ref.watch(categoriesProvider);
    final canEdit = ref.watch(currentLedgerProvider).canEdit;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Categories'),
        actions: [
          if (canEdit) TextButton(onPressed: () => showCategoryEditor(context, ref), child: const Text('Add new')),
          const SizedBox(width: 8),
        ],
      ),
      body: AsyncView(
        value: categories,
        onRetry: () => ref.invalidate(categoriesProvider),
        builder: (categories) => ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            for (final category in categories)
              ListRow(
                leading: CategoryIcon(icon: category.icon, color: category.color),
                title: category.name,
                trailing: canEdit ? Icon(LucideIcons.pencil, size: 16, color: Palette.of(context).subtle) : null,
                onTap: canEdit ? () => showCategoryEditor(context, ref, category: category) : null,
              ),
          ],
        ),
      ),
    );
  }
}
