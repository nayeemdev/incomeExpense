<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Income Expense Manager</title>
    <!-- Custom fonts for this template-->
    <link href="{{ asset('dashboard/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <!-- Custom styles for this template-->
    <link href="{{ asset('dashboard/css/style.css') }}" rel="stylesheet">
    @stack('css')
</head>
<body id="page-top">
    @include('layouts.partials.header')
    <div id="wrapper">
            <!-- Sidebar -->
        @include('layouts.partials.sidebar')
        <div id="content-wrapper">
            @yield('content')
            @include('layouts.partials.calculator')
            <!-- /.container-fluid -->
            <!-- Sticky Footer -->
            @include('layouts.partials.footer')
        </div>
        <!-- /.content-wrapper -->
    </div>
    <!-- /#wrapper -->
    <!-- Scroll to Top Button-->
    {{-- <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a> --}}
    <!-- Bootstrap core JavaScript-->
    <script src="{{ asset('dashboard/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('dashboard/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('dashboard/js/script.js') }}"></script>
    @stack('js')
</body>
</html>
