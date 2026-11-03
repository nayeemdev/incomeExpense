import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

/// Lucide icon names the API may send for a category, mapped to the icon glyphs.
const categoryIcons = <String, IconData>{
  'utensils': LucideIcons.utensils,
  'coffee': LucideIcons.coffee,
  'shopping-cart': LucideIcons.shoppingCart,
  'shopping-bag': LucideIcons.shoppingBag,
  'shirt': LucideIcons.shirt,
  'house': LucideIcons.house,
  'receipt': LucideIcons.receipt,
  'zap': LucideIcons.zap,
  'droplet': LucideIcons.droplet,
  'wifi': LucideIcons.wifi,
  'smartphone': LucideIcons.smartphone,
  'bus': LucideIcons.bus,
  'car': LucideIcons.car,
  'fuel': LucideIcons.fuel,
  'train-front': LucideIcons.trainFront,
  'plane': LucideIcons.plane,
  'hotel': LucideIcons.hotel,
  'clapperboard': LucideIcons.clapperboard,
  'gamepad-2': LucideIcons.gamepad2,
  'music': LucideIcons.music,
  'dumbbell': LucideIcons.dumbbell,
  'heart-pulse': LucideIcons.heartPulse,
  'pill': LucideIcons.pill,
  'stethoscope': LucideIcons.stethoscope,
  'sparkles': LucideIcons.sparkles,
  'scissors': LucideIcons.scissors,
  'graduation-cap': LucideIcons.graduationCap,
  'book-open': LucideIcons.bookOpen,
  'baby': LucideIcons.baby,
  'paw-print': LucideIcons.pawPrint,
  'users': LucideIcons.users,
  'gift': LucideIcons.gift,
  'wrench': LucideIcons.wrench,
  'shield': LucideIcons.shield,
  'landmark': LucideIcons.landmark,
  'percent': LucideIcons.percent,
  'repeat': LucideIcons.repeat,
  'briefcase': LucideIcons.briefcase,
  'banknote': LucideIcons.banknote,
  'building-2': LucideIcons.building2,
  'trending-up': LucideIcons.trendingUp,
  'hand-coins': LucideIcons.handCoins,
  'piggy-bank': LucideIcons.piggyBank,
  'wallet': LucideIcons.wallet,
  'credit-card': LucideIcons.creditCard,
  'tag': LucideIcons.tag,
  'circle-ellipsis': LucideIcons.circleEllipsis,
};

/// Palette names the API may send for a category, as Tailwind 500 shades.
const categoryColors = <String, Color>{
  'red': Color(0xFFEF4444),
  'orange': Color(0xFFF97316),
  'amber': Color(0xFFF59E0B),
  'yellow': Color(0xFFEAB308),
  'lime': Color(0xFF84CC16),
  'green': Color(0xFF22C55E),
  'emerald': Color(0xFF10B981),
  'teal': Color(0xFF14B8A6),
  'cyan': Color(0xFF06B6D4),
  'sky': Color(0xFF0EA5E9),
  'blue': Color(0xFF3B82F6),
  'indigo': Color(0xFF6366F1),
  'violet': Color(0xFF8B5CF6),
  'purple': Color(0xFFA855F7),
  'pink': Color(0xFFEC4899),
  'rose': Color(0xFFF43F5E),
  'slate': Color(0xFF64748B),
};

IconData categoryIcon(String? name) => categoryIcons[name] ?? LucideIcons.tag;

Color categoryColor(String? name) => categoryColors[name] ?? categoryColors['slate']!;

/// A rounded tile with a category's icon on a soft wash of its color.
class CategoryIcon extends StatelessWidget {
  const CategoryIcon({super.key, this.icon, this.color, this.size = 44, this.glyph});

  final String? icon;
  final String? color;
  final double size;

  /// Overrides the icon looked up from [icon], for things that are not categories.
  final IconData? glyph;

  @override
  Widget build(BuildContext context) {
    final tint = categoryColor(color);
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: tint.withValues(alpha: dark ? 0.18 : 0.12),
        borderRadius: BorderRadius.circular(size * 0.32),
      ),
      child: Icon(glyph ?? categoryIcon(icon), size: size * 0.46, color: tint),
    );
  }
}
