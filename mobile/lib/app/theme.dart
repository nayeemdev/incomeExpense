import 'package:flutter/material.dart';

class Palette extends ThemeExtension<Palette> {
  const Palette({
    required this.background,
    required this.foreground,
    required this.muted,
    required this.subtle,
    required this.mutedBg,
    required this.surface,
    required this.card,
    required this.border,
    required this.input,
    required this.primary,
    required this.primaryTint,
    required this.accent,
    required this.accentTint,
    required this.positive,
    required this.positiveTint,
    required this.negative,
    required this.negativeTint,
    required this.warning,
    required this.shadow,
  });

  final Color background;
  final Color foreground;
  final Color muted;
  final Color subtle;
  final Color mutedBg;
  final Color surface;
  final Color card;
  final Color border;
  final Color input;
  final Color primary;
  final Color primaryTint;
  final Color accent;
  final Color accentTint;
  final Color positive;
  final Color positiveTint;
  final Color negative;
  final Color negativeTint;
  final Color warning;
  final List<BoxShadow> shadow;

  static Palette of(BuildContext context) => Theme.of(context).extension<Palette>()!;

  static const light = Palette(
    background: Colors.white,
    foreground: Color(0xFF0F2942),
    muted: Color(0xFF4A6580),
    subtle: Color(0xFF98B2C8),
    mutedBg: Color(0xFFE1E9F0),
    surface: Color(0xFFF2F5F8),
    card: Colors.white,
    border: Color(0xFFD9E2EA),
    input: Color(0xFFCAD6E0),
    primary: Color(0xFF0076A8),
    primaryTint: Color(0xFFEAF6FB),
    accent: Color(0xFF0C4F70),
    accentTint: Color(0xFFCFEAF5),
    positive: Color(0xFF059669),
    positiveTint: Color(0xFFECFDF5),
    negative: Color(0xFFC0261F),
    negativeTint: Color(0xFFFFF4ED),
    warning: Color(0xFFF59E0B),
    shadow: [
      BoxShadow(color: Color(0x0D0F2942), blurRadius: 2, offset: Offset(0, 1)),
      BoxShadow(color: Color(0x140F2942), blurRadius: 14, spreadRadius: -4, offset: Offset(0, 4)),
    ],
  );

  static const dark = Palette(
    background: Color(0xFF08182A),
    foreground: Color(0xFFE6EEF5),
    muted: Color(0xFF8DA4B5),
    subtle: Color(0xFF5E7A93),
    mutedBg: Color(0xFF15304B),
    surface: Color(0xFF0D2238),
    card: Color(0xFF0D2238),
    border: Color(0xFF1D3650),
    input: Color(0xFF23405D),
    primary: Color(0xFF2F9DCA),
    primaryTint: Color(0x332F9DCA),
    accent: Color(0xFF0C4F70),
    accentTint: Color(0xFF15304B),
    positive: Color(0xFF34D399),
    positiveTint: Color(0x2610B981),
    negative: Color(0xFFF68D66),
    negativeTint: Color(0x26F97316),
    warning: Color(0xFFF5B83D),
    shadow: [],
  );

  @override
  Palette copyWith() => this;

  @override
  Palette lerp(Palette? other, double t) => t < 0.5 || other == null ? this : other;
}

const _font = 'Geist';

ThemeData buildTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;
  final palette = dark ? Palette.dark : Palette.light;
  final radius = BorderRadius.circular(12);

  final scheme = ColorScheme.fromSeed(seedColor: palette.primary, brightness: brightness).copyWith(
    primary: palette.primary,
    onPrimary: Colors.white,
    secondary: palette.accent,
    onSecondary: Colors.white,
    surface: palette.background,
    onSurface: palette.foreground,
    onSurfaceVariant: palette.muted,
    surfaceContainerLowest: palette.background,
    surfaceContainerLow: palette.surface,
    surfaceContainer: palette.surface,
    surfaceContainerHigh: palette.card,
    surfaceContainerHighest: palette.mutedBg,
    outline: palette.input,
    outlineVariant: palette.border,
    error: palette.negative,
  );

  final text = ThemeData(brightness: brightness, fontFamily: _font).textTheme
      .copyWith(
        displaySmall: const TextStyle(fontSize: 40, height: 1.05, fontWeight: FontWeight.w700, letterSpacing: -1.4),
        headlineMedium: const TextStyle(fontSize: 26, height: 1.15, fontWeight: FontWeight.w700, letterSpacing: -0.7),
        headlineSmall: const TextStyle(fontSize: 22, height: 1.2, fontWeight: FontWeight.w700, letterSpacing: -0.5),
        titleLarge: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, letterSpacing: -0.3),
        titleMedium: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600, letterSpacing: -0.2),
        titleSmall: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500, letterSpacing: -0.1),
        bodyLarge: const TextStyle(fontSize: 15, height: 1.4),
        bodyMedium: const TextStyle(fontSize: 14, height: 1.4),
        bodySmall: const TextStyle(fontSize: 12.5, height: 1.35),
        labelLarge: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        labelMedium: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w500),
        labelSmall: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, letterSpacing: 0.6),
      )
      .apply(fontFamily: _font, bodyColor: palette.foreground, displayColor: palette.foreground);

  OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
    borderRadius: radius,
    borderSide: BorderSide(color: color, width: width),
  );

  return ThemeData(
    brightness: brightness,
    fontFamily: _font,
    colorScheme: scheme,
    textTheme: text,
    scaffoldBackgroundColor: palette.background,
    extensions: [palette],
    splashFactory: InkSparkle.splashFactory,
    appBarTheme: AppBarTheme(
      backgroundColor: palette.background,
      foregroundColor: palette.foreground,
      surfaceTintColor: Colors.transparent,
      scrolledUnderElevation: 0,
      elevation: 0,
      centerTitle: true,
      titleTextStyle: text.titleMedium,
    ),
    dividerTheme: DividerThemeData(color: palette.border, space: 1, thickness: 1),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: palette.surface,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      hintStyle: TextStyle(color: palette.subtle, fontSize: 15),
      border: border(Colors.transparent),
      enabledBorder: border(Colors.transparent),
      focusedBorder: border(palette.primary, 1.5),
      errorBorder: border(palette.negative),
      focusedErrorBorder: border(palette.negative, 1.5),
      errorStyle: TextStyle(color: palette.negative, fontSize: 12.5),
      prefixIconColor: palette.subtle,
      suffixIconColor: palette.subtle,
      prefixIconConstraints: const BoxConstraints(minWidth: 44, minHeight: 20),
      suffixIconConstraints: const BoxConstraints(minWidth: 44, minHeight: 20),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 52),
        backgroundColor: palette.primary,
        foregroundColor: Colors.white,
        disabledBackgroundColor: palette.primary.withValues(alpha: 0.4),
        disabledForegroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 16, fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 52),
        foregroundColor: palette.foreground,
        side: BorderSide(color: palette.input),
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: palette.primary,
        visualDensity: VisualDensity.compact,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 14, fontWeight: FontWeight.w600),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: palette.foreground,
      contentTextStyle: TextStyle(fontFamily: _font, color: palette.background, fontSize: 14, fontWeight: FontWeight.w500),
      shape: RoundedRectangleBorder(borderRadius: radius),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: palette.card,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
    ),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: palette.card,
      surfaceTintColor: Colors.transparent,
      dragHandleColor: palette.input,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
    ),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: palette.primary, linearTrackColor: palette.mutedBg),
    switchTheme: SwitchThemeData(trackOutlineColor: WidgetStateProperty.all(Colors.transparent)),
    datePickerTheme: DatePickerThemeData(backgroundColor: palette.card, surfaceTintColor: Colors.transparent),
  );
}
