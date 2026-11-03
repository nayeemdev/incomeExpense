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
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AutofillGroup(
                child: Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Center(child: Wordmark()),
                      const SizedBox(height: 28),
                      SurfaceCard(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(_needsCode ? 'Two-factor code' : 'Welcome back', style: text.titleLarge),
                            const SizedBox(height: 2),
                            Text(
                              _needsCode
                                  ? (_useRecovery ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.')
                                  : 'Sign in to your LedgerFly server.',
                              style: text.bodyMedium?.copyWith(color: palette.muted),
                            ),
                            const SizedBox(height: 20),
                            if (!_needsCode) ...[
                              const FieldLabel('Server', required: true),
                              TextFormField(
                                controller: _server,
                                keyboardType: TextInputType.url,
                                autocorrect: false,
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  hintText: 'ledger.example.com',
                                  prefixIcon: Icon(LucideIcons.server, size: 18),
                                ),
                                validator: _required,
                              ),
                              const SizedBox(height: 14),
                              const FieldLabel('Email', required: true),
                              TextFormField(
                                controller: _email,
                                keyboardType: TextInputType.emailAddress,
                                autocorrect: false,
                                autofillHints: const [AutofillHints.email],
                                textInputAction: TextInputAction.next,
                                decoration: const InputDecoration(
                                  hintText: 'you@example.com',
                                  prefixIcon: Icon(LucideIcons.mail, size: 18),
                                ),
                                validator: _required,
                              ),
                              const SizedBox(height: 14),
                              const FieldLabel('Password', required: true),
                              TextFormField(
                                controller: _password,
                                obscureText: _hidePassword,
                                autofillHints: const [AutofillHints.password],
                                onFieldSubmitted: (_) => _submit(),
                                decoration: InputDecoration(
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
                              FieldLabel(_useRecovery ? 'Recovery code' : 'Code', required: true),
                              TextFormField(
                                controller: _code,
                                autofocus: true,
                                autocorrect: false,
                                keyboardType: _useRecovery ? TextInputType.text : TextInputType.number,
                                autofillHints: const [AutofillHints.oneTimeCode],
                                style: const TextStyle(fontSize: 20, letterSpacing: 4, fontWeight: FontWeight.w600),
                                onFieldSubmitted: (_) => _submit(),
                                validator: _required,
                              ),
                              Align(
                                alignment: Alignment.centerLeft,
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
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(color: palette.negativeTint, borderRadius: BorderRadius.circular(8)),
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
                            const SizedBox(height: 20),
                            FilledButton(
                              onPressed: _busy ? null : _submit,
                              child: _busy
                                  ? SizedBox.square(
                                      dimension: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary),
                                    )
                                  : Text(_needsCode ? 'Verify' : 'Sign in'),
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
