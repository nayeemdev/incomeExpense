import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../api/api_client.dart';
import '../app/theme.dart';
import 'format.dart';

class AsyncView<T> extends StatelessWidget {
  const AsyncView({super.key, required this.value, required this.builder, this.onRetry, this.loading});

  final AsyncValue<T> value;
  final Widget Function(T data) builder;
  final VoidCallback? onRetry;
  final Widget? loading;

  @override
  Widget build(BuildContext context) {
    if (value.hasValue) return builder(value.value as T);
    if (value.error case final error?) {
      return EmptyState(
        icon: LucideIcons.wifiOff,
        title: 'Could not load this',
        message: ApiException.from(error).message,
        action: onRetry == null ? null : OutlinedButton(onPressed: onRetry, child: const Text('Try again')),
      );
    }
    return loading ?? const SkeletonList();
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.message, this.action});

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 36),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconBadge(icon, color: palette.primary, size: 64),
          const SizedBox(height: 16),
          Text(title, style: text.titleMedium, textAlign: TextAlign.center),
          if (message != null) ...[
            const SizedBox(height: 4),
            Text(
              message!,
              style: text.bodyMedium?.copyWith(color: palette.muted),
              textAlign: TextAlign.center,
            ),
          ],
          if (action != null) ...[const SizedBox(height: 18), action!],
        ],
      ),
    );
  }
}

/// A soft grey panel. With [elevated] it is a white card with a gentle shadow instead.
class SurfaceCard extends StatelessWidget {
  const SurfaceCard({super.key, required this.child, this.padding = EdgeInsets.zero, this.onTap, this.elevated = false, this.radius = 20});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final bool elevated;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final shape = BorderRadius.circular(radius);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: elevated ? palette.card : palette.surface,
        borderRadius: shape,
        border: elevated && palette.shadow.isEmpty ? Border.all(color: palette.border) : null,
        boxShadow: elevated ? palette.shadow : null,
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: shape,
        clipBehavior: Clip.antiAlias,
        child: onTap == null
            ? Padding(padding: padding, child: child)
            : InkWell(
                onTap: onTap,
                child: Padding(padding: padding, child: child),
              ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.action, this.onAction, this.padding = const EdgeInsets.fromLTRB(20, 24, 12, 8)});

  final String title;
  final String? action;
  final VoidCallback? onAction;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: SizedBox(
        height: 32,
        child: Row(
          children: [
            Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium)),
            if (action != null) TextButton(onPressed: onAction, child: Text(action!)),
          ],
        ),
      ),
    );
  }
}

/// A money amount drawn large, with the cents in a lighter tone like a price tag.
class BigAmount extends StatelessWidget {
  const BigAmount(this.amount, this.currency, {super.key, this.size = 40, this.color, this.decimals});

  final String amount;
  final String currency;
  final double size;
  final Color? color;
  final int? decimals;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = formatMoney(amount, currency, decimals: decimals);
    final separator = NumberFormat.simpleCurrency(name: currency).symbols.DECIMAL_SEP;
    final cut = text.lastIndexOf(separator);
    final whole = cut < 0 ? text : text.substring(0, cut);
    final cents = cut < 0 ? '' : text.substring(cut);
    final style = TextStyle(
      fontSize: size,
      fontWeight: FontWeight.w700,
      letterSpacing: -size * 0.035,
      height: 1.1,
      color: color ?? palette.foreground,
      fontFeatures: const [FontFeature.tabularFigures()],
    );
    return FittedBox(
      fit: BoxFit.scaleDown,
      child: Text.rich(
        TextSpan(
          text: whole,
          style: style,
          children: [
            TextSpan(
              text: cents,
              style: style.copyWith(color: palette.subtle, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

enum MoneyTone { auto, none, positive, negative }

class MoneyText extends StatelessWidget {
  const MoneyText(this.amount, this.currency, {super.key, this.decimals, this.tone = MoneyTone.auto, this.style});

  final String amount;
  final String currency;
  final int? decimals;
  final MoneyTone tone;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final negative = tone == MoneyTone.negative || (tone == MoneyTone.auto && amount.startsWith('-'));
    final color = switch (tone) {
      MoneyTone.none => palette.foreground,
      MoneyTone.positive => palette.positive,
      _ => negative ? palette.negative : palette.foreground,
    };
    return Text(
      formatMoney(amount, currency, decimals: decimals, signed: tone == MoneyTone.positive),
      style: (style ?? const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)).copyWith(
        color: color,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );
  }
}

/// A circle with an icon on a soft wash of [color], or filled with a glow when [filled].
class IconBadge extends StatelessWidget {
  const IconBadge(this.icon, {super.key, required this.color, this.size = 56, this.filled = false});

  final IconData icon;
  final Color color;
  final double size;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: filled ? color : color.withValues(alpha: 0.12),
        boxShadow: filled ? [BoxShadow(color: color.withValues(alpha: 0.35), blurRadius: 24, offset: const Offset(0, 10))] : null,
      ),
      child: Icon(icon, size: size * 0.45, color: filled ? Colors.white : color),
    );
  }
}

class RoundIconButton extends StatelessWidget {
  const RoundIconButton({super.key, required this.icon, required this.onPressed, required this.tooltip, this.badge = 0});

  final IconData icon;
  final VoidCallback? onPressed;
  final String tooltip;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Tooltip(
      message: tooltip,
      child: Material(
        color: palette.surface,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onPressed,
          child: SizedBox.square(
            dimension: 42,
            child: Badge(
              isLabelVisible: badge > 0,
              smallSize: 8,
              backgroundColor: palette.negative,
              offset: const Offset(-2, 2),
              child: Icon(icon, size: 20, color: palette.foreground),
            ),
          ),
        ),
      ),
    );
  }
}

class ProgressBar extends StatelessWidget {
  const ProgressBar({super.key, required this.value, required this.max, this.color, this.height = 8});

  final double value;
  final double max;
  final Color? color;
  final double height;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final ratio = max > 0 ? value / max : 0.0;
    final fill = color ?? (ratio > 1 ? palette.negative : (ratio > 0.85 ? palette.warning : palette.primary));
    return ClipRRect(
      borderRadius: BorderRadius.circular(99),
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: ratio.clamp(0, 1).toDouble()),
        duration: const Duration(milliseconds: 700),
        curve: Curves.easeOutCubic,
        builder: (context, value, _) =>
            LinearProgressIndicator(value: value, minHeight: height, color: fill, backgroundColor: palette.mutedBg),
      ),
    );
  }
}

/// Pill tabs on a soft track, with the selected one raised.
class Segmented<T> extends StatelessWidget {
  const Segmented({super.key, required this.options, required this.value, required this.onChanged, this.colors});

  final Map<T, String> options;
  final T value;
  final ValueChanged<T> onChanged;

  /// Optional text color per option while it is selected, such as red for expenses.
  final Map<T, Color>? colors;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: palette.surface, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          for (final MapEntry(:key, value: label) in options.entries)
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () {
                  HapticFeedback.selectionClick();
                  onChanged(key);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  curve: Curves.easeOutCubic,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: key == value ? (dark ? palette.mutedBg : palette.card) : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                    boxShadow: key == value && !dark
                        ? const [BoxShadow(color: Color(0x140E1726), blurRadius: 8, offset: Offset(0, 2))]
                        : null,
                  ),
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: key == value ? FontWeight.w600 : FontWeight.w500,
                      color: key == value ? (colors?[key] ?? palette.foreground) : palette.muted,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

typedef PickItem = ({String label, String? hint, bool active, Widget? leading});

/// Horizontally scrolling one-tap choices.
class QuickPicks extends StatelessWidget {
  const QuickPicks({super.key, required this.items, required this.onPick, this.padding = EdgeInsets.zero});

  final List<PickItem> items;
  final ValueChanged<int> onPick;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final palette = Palette.of(context);
    return SizedBox(
      height: 38,
      child: ListView.separated(
        padding: padding,
        scrollDirection: Axis.horizontal,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = items[index];
          return Material(
            color: item.active ? palette.primary : palette.surface,
            shape: const StadiumBorder(),
            child: InkWell(
              customBorder: const StadiumBorder(),
              onTap: () {
                HapticFeedback.selectionClick();
                onPick(index);
              },
              child: Padding(
                padding: EdgeInsets.only(left: item.leading == null ? 14 : 5, right: 14),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (item.leading != null) ...[item.leading!, const SizedBox(width: 7)],
                    Text(
                      item.label,
                      style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500, color: item.active ? Colors.white : palette.foreground),
                    ),
                    if (item.hint != null) ...[
                      const SizedBox(width: 6),
                      Text(
                        item.hint!,
                        style: TextStyle(
                          fontSize: 13.5,
                          color: item.active ? Colors.white70 : palette.muted,
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key, this.required = false});

  final String text;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text.rich(
        TextSpan(
          text: text,
          children: [
            if (required)
              TextSpan(
                text: ' *',
                style: TextStyle(color: Palette.of(context).negative),
                semanticsLabel: ' (required)',
              ),
          ],
        ),
        style: Theme.of(context).textTheme.labelLarge,
      ),
    );
  }
}

/// A list row with a leading tile, a title and subtitle, and a trailing widget.
class ListRow extends StatelessWidget {
  const ListRow({super.key, required this.leading, required this.title, this.subtitle, this.trailing, this.onTap});

  final Widget leading;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 9),
        child: Row(
          children: [
            leading,
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: text.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle!,
                      style: text.bodySmall?.copyWith(color: Palette.of(context).muted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ],
              ),
            ),
            if (trailing != null) ...[const SizedBox(width: 12), trailing!],
          ],
        ),
      ),
    );
  }
}

class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.width, this.height = 16, this.radius = 8});

  final double? width;
  final double height;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.4, end: 1.0).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut)),
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(color: Palette.of(context).mutedBg, borderRadius: BorderRadius.circular(widget.radius)),
      ),
    );
  }
}

class SkeletonRow extends StatelessWidget {
  const SkeletonRow({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(horizontal: 20, vertical: 9),
      child: Row(
        children: [
          Skeleton(width: 44, height: 44, radius: 14),
          SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [Skeleton(width: 140, height: 14), SizedBox(height: 8), Skeleton(width: 90, height: 11)],
            ),
          ),
          Skeleton(width: 64, height: 14),
        ],
      ),
    );
  }
}

class SkeletonList extends StatelessWidget {
  const SkeletonList({super.key, this.rows = 6});

  final int rows;

  @override
  Widget build(BuildContext context) => Column(children: [for (var i = 0; i < rows; i++) const SkeletonRow()]);
}

/// A semicircle progress gauge with the brand gradient, as on the goals screen.
class Gauge extends StatelessWidget {
  const Gauge({super.key, required this.progress, required this.child, this.size = 240});

  final double progress;
  final Widget child;
  final double size;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return SizedBox(
      width: size,
      height: size * 0.6,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: progress.clamp(0, 1).toDouble()),
        duration: const Duration(milliseconds: 1000),
        curve: Curves.easeOutCubic,
        builder: (context, value, child) =>
            CustomPaint(painter: _GaugePainter(value, palette.mutedBg, palette.primary, palette.accent), child: child),
        child: Padding(
          padding: EdgeInsets.only(top: size * 0.2),
          child: Align(alignment: Alignment.topCenter, child: child),
        ),
      ),
    );
  }
}

class _GaugePainter extends CustomPainter {
  _GaugePainter(this.progress, this.track, this.start, this.end);

  final double progress;
  final Color track;
  final Color start;
  final Color end;

  @override
  void paint(Canvas canvas, Size size) {
    const stroke = 20.0;
    final rect = Rect.fromCircle(center: Offset(size.width / 2, size.width / 2), radius: size.width / 2 - stroke / 2);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, math.pi, math.pi, false, paint..color = track);
    if (progress <= 0) return;
    paint.color = start;
    canvas.drawArc(rect, math.pi, math.pi * progress, false, paint);
  }

  @override
  bool shouldRepaint(_GaugePainter old) => old.progress != progress || old.start != start || old.track != track;
}

/// Vertical bars with the tallest one highlighted and labelled.
class BarChart extends StatelessWidget {
  const BarChart({super.key, required this.values, required this.labels, required this.currency, this.height = 170});

  final List<double> values;
  final List<String> labels;
  final String currency;
  final double height;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final peak = values.fold<double>(0, math.max);
    final peakIndex = peak == 0 ? -1 : values.indexOf(peak);
    final compact = NumberFormat.compactSimpleCurrency(name: currency);
    final dense = values.length > 12;

    return SizedBox(
      height: height + 52,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (final (index, value) in values.indexed)
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  if (index == peakIndex)
                    FittedBox(
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 6),
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                        decoration: BoxDecoration(color: palette.foreground, borderRadius: BorderRadius.circular(8)),
                        child: Text(
                          compact.format(value),
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: palette.background),
                        ),
                      ),
                    ),
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: peak == 0 ? 0 : value / peak),
                    duration: Duration(milliseconds: 450 + index * 20),
                    curve: Curves.easeOutCubic,
                    builder: (context, ratio, _) => Container(
                      height: math.max(4, height * ratio),
                      margin: EdgeInsets.symmetric(horizontal: dense ? 1.5 : 5),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(dense ? 4 : 10),
                        color: index == peakIndex ? palette.primary : palette.accentTint,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 14,
                    child: Text(
                      dense && index % 5 != 0 ? '' : labels[index],
                      style: TextStyle(fontSize: 10.5, color: palette.muted),
                      maxLines: 1,
                      overflow: TextOverflow.visible,
                      softWrap: false,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// The on-screen number pad used to enter amounts without the system keyboard.
class AmountKeypad extends StatelessWidget {
  const AmountKeypad({super.key, required this.onKey});

  /// Receives a digit, '.', 'back' or 'clear' (long press on back).
  final ValueChanged<String> onKey;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'back'];
    return GridView.count(
      crossAxisCount: 3,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 2.4,
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      children: [
        for (final key in keys)
          Material(
            color: key == 'back' || key == '.' ? Colors.transparent : palette.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () {
                HapticFeedback.lightImpact();
                onKey(key);
              },
              onLongPress: key == 'back' ? () => onKey('clear') : null,
              child: Center(
                child: key == 'back'
                    ? Icon(LucideIcons.delete, size: 22, color: palette.foreground)
                    : Text(
                        key,
                        style: TextStyle(fontSize: 24, fontWeight: FontWeight.w500, color: palette.foreground),
                      ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Applies a keypad key to the amount typed so far, keeping at most [decimals] places.
String applyKey(String current, String key, {int decimals = 2}) {
  switch (key) {
    case 'clear':
      return '';
    case 'back':
      return current.isEmpty ? current : current.substring(0, current.length - 1);
    case '.':
      if (decimals == 0 || current.contains('.')) return current;
      return current.isEmpty ? '0.' : '$current.';
  }
  final dot = current.indexOf('.');
  if (dot >= 0 && current.length - dot > decimals) return current;
  if (current.replaceAll('.', '').length >= 12) return current;
  if (current == '0') return key;
  return '$current$key';
}

/// A centred dialog with a large icon badge, for confirmations like delete or sign out.
Future<bool> showConfirm(
  BuildContext context, {
  required IconData icon,
  required String title,
  required String message,
  required String confirm,
  bool destructive = true,
}) async {
  final palette = Palette.of(context);
  final color = destructive ? palette.negative : palette.primary;
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 30, 24, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconBadge(icon, color: color, size: 80, filled: true),
            const SizedBox(height: 20),
            Text(title, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(color: palette.muted, height: 1.4),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: color),
                    onPressed: () => Navigator.pop(context, true),
                    child: Text(confirm),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
  return result ?? false;
}

Future<void> showSuccess(BuildContext context, {required String title, required String message, String action = 'Done'}) {
  final palette = Palette.of(context);
  HapticFeedback.mediumImpact();
  return showDialog<void>(
    context: context,
    builder: (context) => Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 28),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 30, 24, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TweenAnimationBuilder<double>(
              tween: Tween(begin: 0.5, end: 1),
              duration: const Duration(milliseconds: 600),
              curve: Curves.elasticOut,
              builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
              child: IconBadge(LucideIcons.badgeCheck, color: palette.primary, size: 88, filled: true),
            ),
            const SizedBox(height: 20),
            Text(title, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(color: palette.muted, height: 1.4),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: FilledButton(onPressed: () => Navigator.pop(context), child: Text(action)),
            ),
          ],
        ),
      ),
    ),
  );
}

/// A bottom sheet with a centred title, used for pickers and short forms.
Future<T?> showAppSheet<T>(BuildContext context, {required String title, required WidgetBuilder builder}) {
  return showModalBottomSheet<T>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.85),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
              child: Text(title, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            ),
            Flexible(child: builder(context)),
          ],
        ),
      ),
    ),
  );
}

/// The LedgerFly mark on the brand gradient.
class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 32});

  final double size;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: palette.primary,
        borderRadius: BorderRadius.circular(size * 0.28),
        boxShadow: size > 40
            ? [BoxShadow(color: palette.accent.withValues(alpha: 0.35), blurRadius: size * 0.45, offset: Offset(0, size * 0.14))]
            : null,
      ),
      child: CustomPaint(painter: _LogoPainter()),
    );
  }
}

class _LogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 32);
    final stroke = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(
      Path()
        ..moveTo(9.5, 8)
        ..lineTo(9.5, 21.5)
        ..quadraticBezierTo(9.5, 23.5, 11.5, 23.5)
        ..lineTo(23, 23.5),
      stroke..strokeWidth = 2.75,
    );
    canvas.drawPath(
      Path()
        ..moveTo(14, 18.5)
        ..lineTo(18, 14.5)
        ..lineTo(20.5, 17)
        ..lineTo(25, 12.5)
        ..moveTo(21.5, 12.5)
        ..lineTo(25, 12.5)
        ..lineTo(25, 16),
      stroke
        ..strokeWidth = 2.25
        ..color = Colors.white.withValues(alpha: 0.85),
    );
  }

  @override
  bool shouldRepaint(_LogoPainter old) => false;
}

/// Leaves a pushed screen. Screens opened from a link or widget have nothing underneath, so those go to the dashboard.
void closeScreen(BuildContext context) => context.canPop() ? context.pop() : context.go('/');

void showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiException.from(error).message)));
}

void showToast(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}

/// Axis label for a report bucket starting on [date].
String bucketLabel(DateTime date, String interval) => switch (interval) {
  'day' => DateFormat('d').format(date),
  'week' => DateFormat('d/M').format(date),
  'month' => DateFormat('MMM').format(date),
  _ => DateFormat('y').format(date),
};

typedef DonutSlice = ({String name, double value, Color color});

/// The largest [rows] in the chart palette's fixed order, with the rest folded into "Other".
List<DonutSlice> donutSlices(BuildContext context, List<({String name, double value})> rows) {
  final palette = Palette.of(context);
  final sorted = rows.where((row) => row.value > 0).toList()..sort((a, b) => b.value.compareTo(a.value));
  final shown = sorted.take(palette.chart.length).toList();
  final rest = sorted.skip(palette.chart.length).fold<double>(0, (sum, row) => sum + row.value);
  return [
    for (final (index, row) in shown.indexed) (name: row.name, value: row.value, color: palette.chart[index]),
    if (rest > 0) (name: 'Other', value: rest, color: palette.subtle),
  ];
}

/// A ring chart. Tapping a slice reports its index through [onTap]; [selected] dims the others.
class DonutChart extends StatelessWidget {
  const DonutChart({super.key, required this.slices, required this.center, this.size = 200, this.selected, this.onTap});

  final List<DonutSlice> slices;
  final Widget center;
  final double size;
  final int? selected;
  final ValueChanged<int>? onTap;

  int? _sliceAt(Offset position) {
    final offset = position - Offset(size / 2, size / 2);
    if (offset.distance < size * 0.3 || offset.distance > size / 2) return null;
    final total = slices.fold<double>(0, (sum, slice) => sum + slice.value);
    var angle = math.atan2(offset.dy, offset.dx) + math.pi / 2;
    if (angle < 0) angle += math.pi * 2;
    var end = 0.0;
    for (final (index, slice) in slices.indexed) {
      end += slice.value / total * math.pi * 2;
      if (angle <= end) return index;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapUp: onTap == null
          ? null
          : (details) {
              final index = _sliceAt(details.localPosition);
              if (index == null) return;
              HapticFeedback.selectionClick();
              onTap!(index);
            },
      child: SizedBox.square(
        dimension: size,
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: const Duration(milliseconds: 800),
          curve: Curves.easeOutCubic,
          builder: (context, progress, child) => CustomPaint(painter: _DonutPainter(slices, progress, selected), child: child),
          child: Center(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: KeyedSubtree(key: ValueKey(selected), child: center),
            ),
          ),
        ),
      ),
    );
  }
}

class _DonutPainter extends CustomPainter {
  _DonutPainter(this.slices, this.progress, this.selected);

  final List<DonutSlice> slices;
  final double progress;
  final int? selected;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = size.width * 0.14;
    final rect = Rect.fromCircle(center: size.center(Offset.zero), radius: size.width / 2 - stroke * 0.6);
    final total = slices.fold<double>(0, (sum, slice) => sum + slice.value);
    if (total <= 0) return;
    final paint = Paint()..style = PaintingStyle.stroke;
    final spacing = slices.length > 1 ? 0.035 : 0.0;
    var start = -math.pi / 2;
    for (final (index, slice) in slices.indexed) {
      final sweep = slice.value / total * math.pi * 2 * progress;
      final visible = math.max(0.0, sweep - spacing);
      paint
        ..color = selected != null && selected != index ? slice.color.withValues(alpha: 0.25) : slice.color
        ..strokeWidth = selected == index ? stroke * 1.18 : stroke;
      if (visible > 0) canvas.drawArc(rect, start + spacing / 2, visible, false, paint);
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(_DonutPainter old) => old.progress != progress || old.slices != slices || old.selected != selected;
}
