import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
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
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 40),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(color: palette.mutedBg, borderRadius: BorderRadius.circular(12)),
              child: Icon(icon, size: 22, color: palette.muted),
            ),
            const SizedBox(height: 14),
            Text(title, style: text.titleMedium, textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 4),
              Text(
                message!,
                style: text.bodyMedium?.copyWith(color: palette.muted),
                textAlign: TextAlign.center,
              ),
            ],
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

/// The web app's `surface-card`: white panel, hairline border, soft shadow.
class SurfaceCard extends StatelessWidget {
  const SurfaceCard({super.key, required this.child, this.padding = EdgeInsets.zero, this.onTap});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final radius = BorderRadius.circular(12);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: palette.card,
        borderRadius: radius,
        border: Border.all(color: palette.cardBorder),
        boxShadow: palette.shadow,
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: radius,
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

class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.title, required this.child, this.description, this.action});

  final String title;
  final String? description;
  final Widget? action;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    return SurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.fromLTRB(16, 10, 6, 10),
            decoration: BoxDecoration(
              border: Border(bottom: BorderSide(color: palette.border)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: text.titleMedium),
                      if (description != null) Text(description!, style: text.bodySmall?.copyWith(color: palette.muted)),
                    ],
                  ),
                ),
                ?action,
              ],
            ),
          ),
          child,
        ],
      ),
    );
  }
}

enum Tone { neutral, brand, positive, negative }

class ToneColors {
  const ToneColors(this.background, this.foreground);

  final Color background;
  final Color foreground;

  factory ToneColors.of(BuildContext context, Tone tone) {
    final palette = Palette.of(context);
    return switch (tone) {
      Tone.neutral => ToneColors(palette.mutedBg, palette.foreground),
      Tone.brand => ToneColors(palette.brandTint, palette.brandStrong),
      Tone.positive => ToneColors(palette.positiveTint, palette.positive),
      Tone.negative => ToneColors(palette.negativeTint, palette.negative),
    };
  }
}

class IconTile extends StatelessWidget {
  const IconTile(this.icon, {super.key, this.tone = Tone.neutral, this.size = 36, this.round = false});

  final IconData icon;
  final Tone tone;
  final double size;
  final bool round;

  @override
  Widget build(BuildContext context) {
    final colors = ToneColors.of(context, tone);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: round ? null : BorderRadius.circular(size / 4),
        shape: round ? BoxShape.circle : BoxShape.rectangle,
      ),
      child: Icon(icon, size: size * 0.5, color: colors.foreground),
    );
  }
}

class StatCard extends StatelessWidget {
  const StatCard({super.key, required this.label, required this.value, required this.icon, this.tone = Tone.neutral, this.compact = false});

  final String label;
  final String value;
  final IconData icon;
  final Tone tone;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return SurfaceCard(
      padding: EdgeInsets.all(compact ? 14 : 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(label, style: text.labelLarge?.copyWith(color: Palette.of(context).muted)),
              ),
              IconTile(icon, tone: tone, size: compact ? 30 : 36),
            ],
          ),
          SizedBox(height: compact ? 6 : 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: (compact ? text.titleLarge : text.headlineMedium)?.copyWith(
                fontWeight: FontWeight.w700,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class PageHeader extends StatelessWidget {
  const PageHeader({super.key, required this.title, this.description, this.actions = const []});

  final String title;
  final String? description;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 16, 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: text.headlineMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
                if (description != null) ...[
                  const SizedBox(height: 4),
                  Text(description!, style: text.bodyMedium?.copyWith(color: Palette.of(context).muted)),
                ],
              ],
            ),
          ),
          for (final action in actions) Padding(padding: const EdgeInsets.only(left: 8), child: action),
        ],
      ),
    );
  }
}

/// Square outlined icon button, like the web's `Button variant="outline" size="icon"`.
class SquareIconButton extends StatelessWidget {
  const SquareIconButton({super.key, required this.icon, required this.onPressed, required this.tooltip, this.badge});

  final IconData icon;
  final VoidCallback? onPressed;
  final String tooltip;
  final int? badge;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Tooltip(
      message: tooltip,
      child: Material(
        color: palette.card,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: BorderSide(color: palette.input),
        ),
        child: InkWell(
          onTap: onPressed,
          customBorder: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          child: SizedBox.square(
            dimension: 40,
            child: Badge(
              isLabelVisible: (badge ?? 0) > 0,
              label: Text('${badge ?? ''}'),
              backgroundColor: Theme.of(context).colorScheme.primary,
              offset: const Offset(6, -6),
              child: Icon(icon, size: 18, color: palette.foreground),
            ),
          ),
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
      formatMoney(amount, currency, decimals: decimals),
      style: (style ?? const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)).copyWith(
        color: color,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );
  }
}

class ProgressBar extends StatelessWidget {
  const ProgressBar({super.key, required this.value, required this.max});

  final double value;
  final double max;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final ratio = max > 0 ? value / max : 0.0;
    final color = ratio > 1
        ? palette.negative
        : ratio > 0.85
        ? palette.warning
        : Theme.of(context).colorScheme.primary;
    return ClipRRect(
      borderRadius: BorderRadius.circular(99),
      child: LinearProgressIndicator(value: ratio.clamp(0, 1).toDouble(), minHeight: 8, color: color, backgroundColor: palette.mutedBg),
    );
  }
}

/// Horizontally scrolling one-tap choices, the web app's QuickPicks.
class QuickPicks extends StatelessWidget {
  const QuickPicks({super.key, required this.items, required this.onPick});

  final List<({String label, String? hint, bool active})> items;
  final ValueChanged<int> onPick;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final palette = Palette.of(context);
    final primary = Theme.of(context).colorScheme;
    return SizedBox(
      height: 32,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(width: 6),
        itemBuilder: (context, index) {
          final item = items[index];
          return Material(
            color: item.active ? primary.primary : palette.card,
            shape: StadiumBorder(side: BorderSide(color: item.active ? primary.primary : palette.input)),
            child: InkWell(
              customBorder: const StadiumBorder(),
              onTap: () {
                HapticFeedback.selectionClick();
                onPick(index);
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(item.label, style: TextStyle(fontSize: 13, color: item.active ? primary.onPrimary : palette.foreground)),
                    if (item.hint != null) ...[
                      const SizedBox(width: 6),
                      Text(
                        item.hint!,
                        style: TextStyle(
                          fontSize: 13,
                          color: item.active ? primary.onPrimary.withValues(alpha: 0.8) : palette.muted,
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

/// Pill tabs that sit on the page background, like the web's segmented filters.
class Segmented<T> extends StatelessWidget {
  const Segmented({super.key, required this.options, required this.value, required this.onChanged});

  final Map<T, String> options;
  final T value;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: palette.mutedBg, borderRadius: BorderRadius.circular(10)),
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
                  duration: const Duration(milliseconds: 180),
                  curve: Curves.easeOut,
                  height: 34,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: key == value
                        ? (Theme.of(context).brightness == Brightness.dark ? palette.border : palette.card)
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(8),
                    boxShadow: key == value ? palette.shadow : null,
                  ),
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: key == value ? FontWeight.w600 : FontWeight.w500,
                      color: key == value ? palette.foreground : palette.muted,
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

class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key, this.required = false});

  final String text;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
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

class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.width, this.height = 16, this.radius = 6});

  final double? width;
  final double height;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000))..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.45, end: 1.0).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut)),
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
      padding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Skeleton(width: 34, height: 34, radius: 17),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [Skeleton(width: 140, height: 14), SizedBox(height: 6), Skeleton(width: 90, height: 11)],
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
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      children: [
        SurfaceCard(child: Column(children: [for (var i = 0; i < rows; i++) const SkeletonRow()])),
      ],
    );
  }
}

/// The LedgerFly mark drawn in the brand color, matching the web logo.
class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 32});

  final double size;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return CustomPaint(size: Size.square(size), painter: _LogoPainter(scheme.primary, scheme.onPrimary));
  }
}

class _LogoPainter extends CustomPainter {
  _LogoPainter(this.background, this.foreground);

  final Color background;
  final Color foreground;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 32);
    canvas.drawRRect(RRect.fromLTRBR(0, 0, 32, 32, const Radius.circular(8)), Paint()..color = background);
    final stroke = Paint()
      ..color = foreground
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
        ..color = foreground.withValues(alpha: 0.8),
    );
  }

  @override
  bool shouldRepaint(_LogoPainter old) => old.background != background || old.foreground != foreground;
}

class Wordmark extends StatelessWidget {
  const Wordmark({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const LogoMark(size: 30),
        const SizedBox(width: 8),
        Text.rich(
          TextSpan(
            text: 'Ledger',
            children: [
              TextSpan(
                text: 'Fly',
                style: TextStyle(color: Palette.of(context).muted),
              ),
            ],
          ),
          style: Theme.of(context).textTheme.titleLarge,
        ),
      ],
    );
  }
}

void showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ApiException.from(error).message)));
}
