<div class="nav-group">
    <nav class="nav-item">
        @if (!empty($nav_is_clocked_out) && $nav_is_clocked_out)
            <a href="{{ route('attendance.list') }}" class="nav-link">今月の出勤一覧</a>
            <a href="{{ route('stamp_correction_request.list') }}" class="nav-link">申請一覧</a>
        @else
            <a href="{{ route('attendance.index') }}" class="nav-link">勤怠</a>
            <a href="{{ route('attendance.list') }}" class="nav-link">勤怠一覧</a>
            <a href="{{ route('stamp_correction_request.list') }}" class="nav-link">申請</a>
        @endif
            <form class="logout-form" action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="nav-link__logout" type="submit">ログアウト</button>
            </form>
    </nav>
</div>