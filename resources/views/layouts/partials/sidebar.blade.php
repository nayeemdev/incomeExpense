<ul class="sidebar navbar-nav">
    <li class="nav-item active">
        <a class="nav-link" href="{{ route('index') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('incomes.index') }}">
        <i class="fas fa-fw fa-dollar-sign"></i>
        <span>Incomes</span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('expense.index') }}">
        <i class="fas fa-fw fa-money-bill"></i>
        <span>Expenses</span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('summary') }}">
        <i class="fas fa-fw fa-table"></i>
        <span>All Summary</span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
        <i class="fas fa-fw fa-table"></i>
        <span>Monthly View</span></a>
    </li>
    <li class="nav-item" title="This is not calculate in Income/Expense">
        <a class="nav-link" href="{{ route('notes.index') }}">
        <i class="fas fa-fw fa-sticky-note"></i>
        <span>Notes</span></a>
    </li>
</ul>
