import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import '../../api/api_client.dart';
import '../../app/theme.dart';
import '../../shared/widgets.dart';
import 'session.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _server = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  bool _needsCode = false;
  bool _useRecovery = false;
  bool _busy = false;
  bool _hidePassword = true;
  bool _editServer = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    ref.read(sessionProvider.notifier).lastServer().then((value) {
      if (value != null && mounted && _server.text.isEmpty) {
        setState(() {
          _server.text = value;
          _editServer = false;
        });
      }
    });
  }

  @override
  void dispose() {
    for (final controller in [_server, _email, _password, _code]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final code = _code.text.trim();
    try {
      await ref
          .read(sessionProvider.notifier)
          .signIn(
            server: _normalizedServer(),
            email: _email.text.trim(),
            password: _password.text,
            code: _needsCode && !_useRecovery ? code : null,
            recoveryCode: _needsCode && _useRecovery ? code : null,
          );
    } on TwoFactorRequired {
      setState(() => _needsCode = true);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _normalizedServer() {
    final value = _server.text.trim();
    return value.contains('://') ? value : 'https://$value';
  }

  String? _required(String? value) => (value == null || value.trim().isEmpty) ? 'Required' : null;

  String get _host => Uri.tryParse(_normalizedServer())?.authority ?? _server.text.trim();

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    final dark = Theme.of(context).brightness == Brightness.dark;
    final hero = dark ? const Color(0xFF0C2239) : palette.primary;

    return AnnotatedRegion(
      value: const SystemUiOverlayStyle(statusBarBrightness: Brightness.dark, statusBarIconBrightness: Brightness.light),
      child: Scaffold(
        backgroundColor: hero,
        body: Stack(
          children: [
            Positioned(top: -90, right: -70, child: _Ring(size: 260, color: Colors.white.withValues(alpha: 0.07))),
            Positioned(top: 120, left: -60, child: _Ring(size: 160, color: Colors.white.withValues(alpha: 0.05))),
            SafeArea(
              bottom: false,
              child: LayoutBuilder(
                builder: (context, constraints) => SingleChildScrollView(
                  child: ConstrainedBox(
                    constraints: BoxConstraints(minHeight: constraints.maxHeight),
                    child: IntrinsicHeight(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Padding(
                            padding: const EdgeInsets.fromLTRB(28, 36, 28, 32),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                LogoMark(size: 60, background: Colors.white, foreground: hero),
                                const SizedBox(height: 22),
                                Text(
                                  'LedgerFly',
                                  style: text.headlineMedium?.copyWith(color: Colors.white, fontSize: 32, letterSpacing: -1),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'Track spending, budgets and goals in one place.',
                                  style: text.bodyLarge?.copyWith(color: Colors.white.withValues(alpha: 0.75)),
                                ),
                              ],
                            ),
                          ),
                          Expanded(
                            child: Container(
                              decoration: BoxDecoration(
                                color: palette.background,
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(32)),
                              ),
                              padding: EdgeInsets.fromLTRB(24, 30, 24, 24 + MediaQuery.paddingOf(context).bottom),
                              child: Center(
                                child: ConstrainedBox(
                                  constraints: const BoxConstraints(maxWidth: 440),
                                  child: AutofillGroup(
                                    child: Form(key: _form, child: _formBody(context)),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _formBody(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisAlignment: MainAxisAlignment.start,
      children: [
        Text(_needsCode ? 'Two-factor code' : 'Welcome back', style: text.headlineSmall),
        const SizedBox(height: 4),
        Text(
          _needsCode
              ? (_useRecovery ? 'Enter one of your recovery codes.' : 'Enter the 6 digit code from your authenticator app.')
              : 'Sign in to keep track of your money.',
          style: text.bodyMedium?.copyWith(color: palette.muted),
        ),
        const SizedBox(height: 26),
        if (!_needsCode) ...[
          if (_editServer) ...[
            const FieldLabel('Server', required: true),
            TextFormField(
              controller: _server,
              keyboardType: TextInputType.url,
              autocorrect: false,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(hintText: 'ledger.example.com', prefixIcon: Icon(LucideIcons.server, size: 18)),
              validator: _required,
            ),
          ] else
            Material(
              color: palette.surface,
              borderRadius: BorderRadius.circular(14),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 6, 6, 6),
                child: Row(
                  children: [
                    Icon(LucideIcons.server, size: 18, color: palette.muted),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _host,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
                      ),
                    ),
                    TextButton(onPressed: () => setState(() => _editServer = true), child: const Text('Change')),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),
          const FieldLabel('Email', required: true),
          TextFormField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            autofillHints: const [AutofillHints.email],
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(hintText: 'you@example.com', prefixIcon: Icon(LucideIcons.mail, size: 18)),
            validator: _required,
          ),
          const SizedBox(height: 16),
          const FieldLabel('Password', required: true),
          TextFormField(
            controller: _password,
            obscureText: _hidePassword,
            autofillHints: const [AutofillHints.password],
            onFieldSubmitted: (_) => _submit(),
            decoration: InputDecoration(
              hintText: 'Your password',
              prefixIcon: const Icon(LucideIcons.lockKeyhole, size: 18),
              suffixIcon: IconButton(
                tooltip: _hidePassword ? 'Show password' : 'Hide password',
                icon: Icon(_hidePassword ? LucideIcons.eye : LucideIcons.eyeOff, size: 18),
                onPressed: () => setState(() => _hidePassword = !_hidePassword),
              ),
            ),
            validator: _required,
          ),
        ] else ...[
          TextFormField(
            controller: _code,
            autofocus: true,
            autocorrect: false,
            textAlign: TextAlign.center,
            keyboardType: _useRecovery ? TextInputType.text : TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            style: const TextStyle(fontSize: 26, letterSpacing: 10, fontWeight: FontWeight.w600),
            decoration: InputDecoration(hintText: _useRecovery ? 'Recovery code' : '000000'),
            onFieldSubmitted: (_) => _submit(),
            validator: _required,
          ),
          const SizedBox(height: 4),
          Align(
            child: TextButton(
              onPressed: () => setState(() {
                _useRecovery = !_useRecovery;
                _code.clear();
              }),
              child: Text(_useRecovery ? 'Use an authenticator code' : 'Use a recovery code'),
            ),
          ),
        ],
        if (_error != null) ...[
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: palette.negativeTint, borderRadius: BorderRadius.circular(12)),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(LucideIcons.circleAlert, size: 18, color: palette.negative),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(_error!, style: text.bodyMedium?.copyWith(color: palette.negative)),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 26),
        FilledButton(
          onPressed: _busy ? () {} : _submit,
          child: _busy
              ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(_needsCode ? 'Verify' : 'Sign in'),
                    const SizedBox(width: 8),
                    const Icon(LucideIcons.arrowRight, size: 18),
                  ],
                ),
        ),
        if (_needsCode)
          TextButton(
            onPressed: () => setState(() {
              _needsCode = false;
              _code.clear();
              _error = null;
            }),
            child: const Text('Back'),
          ),
      ],
    );
  }
}

/// A soft outlined circle used as decoration on the brand header.
class _Ring extends StatelessWidget {
  const _Ring({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: color, width: 28),
    ),
  );
}
