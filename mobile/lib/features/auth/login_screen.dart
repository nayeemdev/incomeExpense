import 'package:flutter/material.dart';
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
  String? _error;

  @override
  void initState() {
    super.initState();
    ref.read(sessionProvider.notifier).lastServer().then((value) {
      if (value != null && mounted && _server.text.isEmpty) _server.text = value;
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

  @override
  Widget build(BuildContext context) {
    final palette = Palette.of(context);
    final text = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AutofillGroup(
                child: Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Center(child: LogoMark(size: 76)),
                      const SizedBox(height: 24),
                      Text(_needsCode ? 'Two-factor code' : 'Welcome to LedgerFly', style: text.headlineSmall, textAlign: TextAlign.center),
                      const SizedBox(height: 6),
                      Text(
                        _needsCode
                            ? (_useRecovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.')
                            : 'Enter your server, email and password to sign in.',
                        style: text.bodyMedium?.copyWith(color: palette.muted),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 32),
                      if (!_needsCode) ...[
                        TextFormField(
                          controller: _server,
                          keyboardType: TextInputType.url,
                          autocorrect: false,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            hintText: 'Server, e.g. ledger.example.com',
                            prefixIcon: Icon(LucideIcons.server, size: 18),
                          ),
                          validator: _required,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          autocorrect: false,
                          autofillHints: const [AutofillHints.email],
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(hintText: 'Email', prefixIcon: Icon(LucideIcons.mail, size: 18)),
                          validator: _required,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _password,
                          obscureText: _hidePassword,
                          autofillHints: const [AutofillHints.password],
                          onFieldSubmitted: (_) => _submit(),
                          decoration: InputDecoration(
                            hintText: 'Password',
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
                          style: const TextStyle(fontSize: 24, letterSpacing: 8, fontWeight: FontWeight.w600),
                          decoration: InputDecoration(hintText: _useRecovery ? 'Recovery code' : '000000'),
                          onFieldSubmitted: (_) => _submit(),
                          validator: _required,
                        ),
                        TextButton(
                          onPressed: () => setState(() {
                            _useRecovery = !_useRecovery;
                            _code.clear();
                          }),
                          child: Text(_useRecovery ? 'Use an authenticator code' : 'Use a recovery code'),
                        ),
                      ],
                      if (_error != null) ...[
                        const SizedBox(height: 14),
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
                      const SizedBox(height: 24),
                      FilledButton(
                        onPressed: _busy ? () {} : _submit,
                        child: _busy
                            ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
                            : Text(_needsCode ? 'Confirm' : 'Sign in'),
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
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
