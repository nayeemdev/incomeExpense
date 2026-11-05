import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/models.dart';
import '../../app/theme.dart';
import '../../shared/format.dart';

/// Milestones and the running total of payouts, solid for what arrived and dashed for what is still to come,
/// against a line at what was put in.
class ReturnRoadmap extends StatelessWidget {
  const ReturnRoadmap({super.key, required this.roadmap, required this.invested, required this.currency});

  final InvestmentRoadmap roadmap;
  final String invested;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final milestones = [
      if (roadmap.points.isNotEmpty) (LucideIcons.handCoins, 'Return by ${formatDate(roadmap.end)}', formatMoney(roadmap.totalReturn, currency), null),
      if (roadmap.breakEven != null)
        (LucideIcons.flag, 'Money back', formatDate(roadmap.breakEven!), 'Payouts cover the ${formatMoney(invested, currency)} put in'),
      if (roadmap.installmentsLeft > 0)
        (
          LucideIcons.calendarSync,
          'Installments left',
          '${roadmap.installmentsLeft}',
          roadmap.installmentsTotal == null ? null : '${formatMoney(roadmap.installmentsTotal!, currency)} still to pay in',
        ),
      if (roadmap.maturityValue != null)
        (
          LucideIcons.trophy,
          'Value at maturity',
          formatMoney(roadmap.maturityValue!, currency),
          roadmap.maturityGain == null ? null : '${formatMoney(roadmap.maturityGain!, currency)} more than put in',
        ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (icon, label, value, hint) in milestones)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              children: [
                Icon(icon, size: 18, color: palette.primary),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(label, style: TextStyle(color: palette.muted, fontSize: 12.5)),
                      if (hint != null) Text(hint, style: TextStyle(color: palette.subtle, fontSize: 11.5)),
                    ],
                  ),
                ),
                Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        if (roadmap.points.length > 1) ...[
          const SizedBox(height: 8),
          SizedBox(
            height: 170,
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: 1),
              duration: const Duration(milliseconds: 900),
              curve: Curves.easeOutCubic,
              builder: (context, progress, _) => CustomPaint(
                painter: _RoadmapPainter(
                  points: roadmap.points,
                  invested: double.tryParse(invested) ?? 0,
                  breakEven: roadmap.breakEven,
                  progress: progress,
                  received: palette.chart[2],
                  expected: palette.chart[0],
                  marker: palette.chart[3],
                  guide: palette.subtle,
                  background: palette.card,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(DateFormat('MMM y').format(roadmap.points.first.date), style: TextStyle(color: palette.muted, fontSize: 11)),
              Text(DateFormat('MMM y').format(roadmap.points.last.date), style: TextStyle(color: palette.muted, fontSize: 11)),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 14,
            runSpacing: 4,
            children: [
              _Legend(color: palette.chart[2], label: 'Received'),
              _Legend(color: palette.chart[0], label: 'Expected'),
              if (roadmap.breakEven != null) _Legend(color: palette.chart[3], label: 'Money back', dot: true),
            ],
          ),
        ],
      ],
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label, this.dot = false});

  final Color color;
  final String label;
  final bool dot;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: dot ? 9 : 14,
          height: dot ? 9 : 3,
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(4)),
        ),
        const SizedBox(width: 6),
        Text(label, style: TextStyle(color: Palette.of(context).muted, fontSize: 11.5)),
      ],
    );
  }
}

class _RoadmapPainter extends CustomPainter {
  _RoadmapPainter({
    required this.points,
    required this.invested,
    required this.breakEven,
    required this.progress,
    required this.received,
    required this.expected,
    required this.marker,
    required this.guide,
    required this.background,
  });

  final List<RoadmapPoint> points;
  final double invested;
  final DateTime? breakEven;
  final double progress;
  final Color received;
  final Color expected;
  final Color marker;
  final Color guide;
  final Color background;

  @override
  void paint(Canvas canvas, Size size) {
    final start = points.first.date.millisecondsSinceEpoch.toDouble();
    final span = math.max(1, points.last.date.millisecondsSinceEpoch - start);
    final top = math.max(invested, points.last.cumulative) * 1.08;
    Offset at(DateTime date, double value) =>
        Offset((date.millisecondsSinceEpoch - start) / span * size.width, size.height - (top == 0 ? 0 : value / top * size.height));

    if (invested > 0) {
      final y = at(points.first.date, invested).dy;
      _dashed(
        canvas,
        Offset(0, y),
        Offset(size.width, y),
        Paint()
          ..color = guide
          ..strokeWidth = 1,
      );
    }

    final lastReceived = points.lastIndexWhere((point) => point.status == 'received');
    final visible = (points.length * progress).ceil().clamp(1, points.length);
    Path stepPath(int from, int to) {
      final origin = at(points[from].date, from == 0 ? 0 : points[from - 1].cumulative);
      final path = Path()..moveTo(origin.dx, origin.dy);
      for (var i = from; i <= to; i++) {
        final previous = i == 0 ? 0.0 : points[i - 1].cumulative;
        final before = at(points[i].date, previous);
        final after = at(points[i].date, points[i].cumulative);
        path
          ..lineTo(before.dx, before.dy)
          ..lineTo(after.dx, after.dy);
      }
      return path;
    }

    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeJoin = StrokeJoin.round;
    final end = visible - 1;

    if (lastReceived >= 0) {
      canvas.drawPath(stepPath(0, math.min(lastReceived, end)), stroke..color = received);
    }
    if (end > lastReceived) {
      final from = math.max(lastReceived + 1, 0);
      for (final metric in stepPath(from, end).computeMetrics()) {
        for (var distance = 0.0; distance < metric.length; distance += 9) {
          canvas.drawPath(metric.extractPath(distance, math.min(distance + 5, metric.length)), stroke..color = expected);
        }
      }
    }

    final mark = breakEven;
    if (mark != null && progress >= 1) {
      final center = at(mark, invested);
      canvas.drawCircle(center, 6, Paint()..color = background);
      canvas.drawCircle(center, 4.5, Paint()..color = marker);
    }
  }

  void _dashed(Canvas canvas, Offset from, Offset to, Paint paint) {
    for (var x = from.dx; x < to.dx; x += 8) {
      canvas.drawLine(Offset(x, from.dy), Offset(math.min(x + 4, to.dx), to.dy), paint);
    }
  }

  @override
  bool shouldRepaint(_RoadmapPainter old) => old.progress != progress || old.points != points;
}
