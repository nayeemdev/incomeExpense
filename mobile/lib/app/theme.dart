import 'package:flutter/material.dart';

/// Design tokens mirrored from the web app's index.css so both apps look like one product.
class Palette extends ThemeExtension<Palette> {
  const Palette({
    required this.foreground,
    required this.muted,
    required this.mutedBg,
    required this.surface,
    required this.card,
    required this.border,
    required this.cardBorder,
    required this.input,
    required this.brandTint,
    required this.brandStrong,
    required this.positive,
    required this.positiveTint,
    required this.negative,
    required this.negativeTint,
    required this.warning,
    required this.shadow,
  });

  final Color foreground;
  final Color muted;
  final Color mutedBg;
  final Color surface;
  final Color card;
  final Color border;
  final Color cardBorder;
  final Color input;
  final Color brandTint;
  final Color brandStrong;
  final Color positive;
  final Color positiveTint;
  final Color negative;
  final Color negativeTint;
  final Color warning;
  final List<BoxShadow> shadow;

  static Palette of(BuildContext context) => Theme.of(context).extension<Palette>()!;

  static const light = Palette(
    foreground: Color(0xFF0F2942),
    muted: Color(0xFF4A6580),
    mutedBg: Color(0xFFF1F5F8),
    surface: Color(0xFFF2F5F8),
    card: Colors.white,
    border: Color(0xFFD9E2EA),
    cardBorder: Color(0x120F2942),
    input: Color(0xFFCAD6E0),
    brandTint: Color(0xFFEAF6FB),
    brandStrong: Color(0xFF075F88),
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
    foreground: Color(0xFFE6EEF5),
    muted: Color(0xFF8DA4B5),
    mutedBg: Color(0xFF13293F),
    surface: Color(0xFF0B1E33),
    card: Color(0xFF0D2238),
    border: Color(0xFF1D3650),
    cardBorder: Color(0xFF1D3650),
    input: Color(0xFF23405D),
    brandTint: Color(0x660F425D),
    brandStrong: Color(0xFFA2D6EB),
    positive: Color(0xFF34D399),
    positiveTint: Color(0x2610B981),
    negative: Color(0xFFF68D66),
    negativeTint: Color(0x26F97316),
    warning: Color(0xFFF59E0B),
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
  final primary = dark ? const Color(0xFF2F9DCA) : const Color(0xFF0076A8);
  final onPrimary = dark ? const Color(0xFF04121F) : Colors.white;
  final radius = BorderRadius.circular(8);

  final scheme = ColorScheme.fromSeed(seedColor: primary, brightness: brightness).copyWith(
    primary: primary,
    onPrimary: onPrimary,
    surface: palette.card,
    onSurface: palette.foreground,
    onSurfaceVariant: palette.muted,
    surfaceContainerLowest: palette.surface,
    surfaceContainerLow: palette.card,
    surfaceContainer: palette.card,
    surfaceContainerHigh: palette.card,
    surfaceContainerHighest: palette.mutedBg,
    outline: palette.input,
    outlineVariant: palette.border,
    error: palette.negative,
  );

  final base = ThemeData(brightness: brightness, fontFamily: _font).textTheme;
  final text = base
      .copyWith(
        headlineMedium: const TextStyle(fontSize: 28, height: 1.15, fontWeight: FontWeight.w700, letterSpacing: -0.6),
        headlineSmall: const TextStyle(fontSize: 24, height: 1.2, fontWeight: FontWeight.w700, letterSpacing: -0.5),
        titleLarge: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, letterSpacing: -0.2),
        titleMedium: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600, letterSpacing: -0.1),
        titleSmall: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
        bodyLarge: const TextStyle(fontSize: 15, height: 1.4),
        bodyMedium: const TextStyle(fontSize: 14, height: 1.4),
        bodySmall: const TextStyle(fontSize: 12.5, height: 1.35),
        labelLarge: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        labelMedium: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w500),
        labelSmall: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, letterSpacing: 0.5),
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
    scaffoldBackgroundColor: palette.surface,
    extensions: [palette],
    splashFactory: InkSparkle.splashFactory,
    appBarTheme: AppBarTheme(
      backgroundColor: palette.surface,
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
      fillColor: palette.card,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      hintStyle: TextStyle(color: palette.muted, fontSize: 15),
      border: border(palette.input),
      enabledBorder: border(palette.input),
      focusedBorder: border(primary, 1.5),
      errorBorder: border(palette.negative),
      focusedErrorBorder: border(palette.negative, 1.5),
      errorStyle: TextStyle(color: palette.negative, fontSize: 12.5),
      suffixIconColor: palette.muted,
      suffixIconConstraints: const BoxConstraints(minWidth: 40, minHeight: 20),
      prefixIconConstraints: const BoxConstraints(minWidth: 40, minHeight: 20),
      prefixIconColor: palette.muted,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 44),
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 44),
        foregroundColor: palette.foreground,
        side: BorderSide(color: palette.input),
        backgroundColor: palette.card,
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 14, fontWeight: FontWeight.w500),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        visualDensity: VisualDensity.compact,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        shape: RoundedRectangleBorder(borderRadius: radius),
        textStyle: const TextStyle(fontFamily: _font, fontSize: 14, fontWeight: FontWeight.w600),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: palette.card,
      surfaceTintColor: Colors.transparent,
      indicatorColor: palette.brandTint,
      height: 64,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontFamily: _font,
          fontSize: 12,
          fontWeight: states.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w500,
          color: states.contains(WidgetState.selected) ? palette.foreground : palette.muted,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(size: 22, color: states.contains(WidgetState.selected) ? palette.brandStrong : palette.muted),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: palette.foreground,
      contentTextStyle: TextStyle(fontFamily: _font, color: palette.card, fontSize: 14),
      shape: RoundedRectangleBorder(borderRadius: radius),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: palette.card,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    bottomSheetTheme: BottomSheetThemeData(backgroundColor: palette.card, surfaceTintColor: Colors.transparent),
    popupMenuTheme: PopupMenuThemeData(color: palette.card, surfaceTintColor: Colors.transparent),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: primary, linearTrackColor: palette.mutedBg),
    switchTheme: SwitchThemeData(trackOutlineColor: WidgetStateProperty.all(Colors.transparent)),
    listTileTheme: ListTileThemeData(iconColor: palette.muted, contentPadding: const EdgeInsets.symmetric(horizontal: 16)),
  );
}
