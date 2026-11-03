import 'package:flutter/material.dart';

class Palette extends ThemeExtension<Palette> {
  const Palette({required this.income, required this.expense, required this.muted, required this.surface, required this.border});

  final Color income;
  final Color expense;
  final Color muted;
  final Color surface;
  final Color border;

  static Palette of(BuildContext context) => Theme.of(context).extension<Palette>()!;

  @override
  Palette copyWith({Color? income, Color? expense, Color? muted, Color? surface, Color? border}) => Palette(
        income: income ?? this.income,
        expense: expense ?? this.expense,
        muted: muted ?? this.muted,
        surface: surface ?? this.surface,
        border: border ?? this.border,
      );

  @override
  Palette lerp(Palette? other, double t) => other == null
      ? this
      : Palette(
          income: Color.lerp(income, other.income, t)!,
          expense: Color.lerp(expense, other.expense, t)!,
          muted: Color.lerp(muted, other.muted, t)!,
          surface: Color.lerp(surface, other.surface, t)!,
          border: Color.lerp(border, other.border, t)!,
        );
}

ThemeData buildTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;
  final palette = dark
      ? const Palette(
          income: Color(0xFF4CC795),
          expense: Color(0xFFF68D66),
          muted: Color(0xFF8DA4B5),
          surface: Color(0xFF0B1E33),
          border: Color(0xFF1D3650),
        )
      : const Palette(
          income: Color(0xFF0F7A52),
          expense: Color(0xFFC0261F),
          muted: Color(0xFF4A6580),
          surface: Color(0xFFF2F5F8),
          border: Color(0xFFD9E2EA),
        );
  final foreground = dark ? const Color(0xFFE6EEF5) : const Color(0xFF0F2942);
  final card = dark ? const Color(0xFF0D2238) : Colors.white;
  final scheme = ColorScheme.fromSeed(
    seedColor: const Color(0xFF0076A8),
    brightness: brightness,
  ).copyWith(
    primary: dark ? const Color(0xFF2F9DCA) : const Color(0xFF0076A8),
    onPrimary: dark ? const Color(0xFF04121F) : Colors.white,
    surface: card,
    onSurface: foreground,
    onSurfaceVariant: palette.muted,
    surfaceContainerLowest: palette.surface,
    surfaceContainerLow: card,
    surfaceContainer: card,
    surfaceContainerHigh: card,
    outline: palette.border,
    outlineVariant: palette.border,
    error: palette.expense,
  );
  final radius = BorderRadius.circular(10);
  final fieldBorder = OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: dark ? const Color(0xFF23405D) : const Color(0xFFCAD6E0)));

  return ThemeData(
    colorScheme: scheme,
    scaffoldBackgroundColor: palette.surface,
    extensions: [palette],
    appBarTheme: AppBarTheme(
      backgroundColor: palette.surface,
      foregroundColor: foreground,
      surfaceTintColor: Colors.transparent,
      centerTitle: false,
      titleTextStyle: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: foreground),
    ),
    cardTheme: CardThemeData(
      color: card,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: palette.border)),
    ),
    dividerTheme: DividerThemeData(color: palette.border, space: 1, thickness: 1),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: card,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      border: fieldBorder,
      enabledBorder: fieldBorder,
      focusedBorder: fieldBorder.copyWith(borderSide: BorderSide(color: scheme.primary, width: 1.5)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: const Size(0, 48), shape: RoundedRectangleBorder(borderRadius: radius)),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(minimumSize: const Size(0, 48), shape: RoundedRectangleBorder(borderRadius: radius)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: card,
      indicatorColor: scheme.primary.withValues(alpha: 0.14),
      surfaceTintColor: Colors.transparent,
    ),
    listTileTheme: ListTileThemeData(iconColor: palette.muted),
  );
}
