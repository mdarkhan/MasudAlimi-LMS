@props(['title' => null, 'heading' => null])

@php
    $user = auth()->user();
    $nav = [
        ['label' => __('nav.dashboard'), 'group' => 'main', 'href' => route('admin.dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('admin.dashboard')],
        ['label' => __('nav.students'), 'group' => 'academic', 'href' => route('admin.students.index'), 'icon' => 'students', 'active' => request()->routeIs('admin.students.*')],
        ['label' => __('nav.points'), 'group' => 'academic', 'href' => route('admin.points.index'), 'icon' => 'points', 'active' => request()->routeIs('admin.points.*')],
        ['label' => __('nav.courses'), 'group' => 'academic', 'href' => route('admin.courses.index'), 'icon' => 'courses', 'active' => request()->routeIs('admin.courses.*')],
        ['label' => __('nav.lessons'), 'group' => 'academic', 'href' => route('admin.lessons.index'), 'icon' => 'lessons', 'active' => request()->routeIs('admin.lessons.*')],
    ];

    // Students' name/guardian correction requests waiting for a decision.
    if ($user?->hasPermission('students.update')) {
        $pendingRequests = app(\App\Services\Profile\ProfileChangeService::class)->pendingCount();
        $nav[] = ['label' => __('nav.profile_requests'), 'group' => 'academic', 'href' => route('admin.profile-requests.index'), 'icon' => 'edit', 'active' => request()->routeIs('admin.profile-requests.*'), 'badge' => $pendingRequests ?: null];
    }

    if ($user?->hasPermission('books.manage')) {
        $nav[] = ['label' => __('nav.books'), 'group' => 'content', 'href' => route('admin.books.index'), 'icon' => 'book', 'active' => request()->routeIs('admin.books.*')];
    }

    // Quiz section only for users who may view quizzes.
    if ($user?->hasPermission('quizzes.view')) {
        $nav[] = ['label' => __('nav.quizzes'), 'group' => 'academic', 'href' => route('admin.quizzes.index'), 'icon' => 'exam', 'active' => request()->routeIs('admin.quizzes.*')];
    }

    if ($user?->hasPermission('results.view')) {
        $nav[] = ['label' => __('nav.results'), 'group' => 'academic', 'href' => route('admin.results.index'), 'icon' => 'results', 'active' => request()->routeIs('admin.results.*')];
        $nav[] = ['label' => __('nav.leaderboard'), 'group' => 'academic', 'href' => route('admin.leaderboards.index'), 'icon' => 'leaderboard', 'active' => request()->routeIs('admin.leaderboards.*')];
    }

    if ($user?->hasPermission('posts.manage')) {
        $nav[] = ['label' => __('nav.posts'), 'group' => 'content', 'href' => route('admin.posts.index'), 'icon' => 'book', 'active' => request()->routeIs('admin.posts.*')];
    }

    // Unread student messages across every thread — the shared-inbox badge.
    if ($user?->hasPermission('messages.view')) {
        $unreadMessages = app(\App\Services\Messaging\MessageService::class)->unreadCountFor($user);
        $nav[] = ['label' => __('nav.messages'), 'group' => 'content', 'href' => route('admin.messages.index'), 'icon' => 'message', 'active' => request()->routeIs('admin.messages.*'), 'badge' => $unreadMessages ?: null];
    }

    if ($user?->hasPermission('amol.view')) {
        $nav[] = ['label' => __('nav.amol'), 'group' => 'content', 'href' => route('admin.amol.index'), 'icon' => 'checklist', 'active' => request()->routeIs('admin.amol.*')];
    }

    if ($user?->hasPermission('notices.manage')) {
        $nav[] = ['label' => __('nav.notices'), 'group' => 'content', 'href' => route('admin.notices.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.notices.*')];
    }

    if ($user?->hasPermission('faqs.manage')) {
        $nav[] = ['label' => __('nav.faqs'), 'group' => 'content', 'href' => route('admin.faqs.index'), 'icon' => 'message', 'active' => request()->routeIs('admin.faqs.*')];
    }

    if ($user?->hasPermission('settings.manage')) {
        $nav[] = ['label' => __('nav.settings'), 'group' => 'system', 'href' => route('admin.settings.edit'), 'icon' => 'settings', 'active' => request()->routeIs('admin.settings.*')];
    }

    $nav[] = ['label' => __('nav.audit'), 'group' => 'system', 'href' => route('admin.audit.index'), 'icon' => 'audit', 'active' => request()->routeIs('admin.audit.*')];
    $nav[] = ['label' => __('nav.account'), 'group' => 'system', 'href' => route('admin.account.edit'), 'icon' => 'profile', 'active' => request()->routeIs('admin.account.*')];

    // Staff & role management — super_admin only, deliberately not a perm: gate (see
    // routes/web.php): a regular admin must never see or reach this area.
    if ($user?->hasRole(\App\Models\Role::SUPER_ADMIN)) {
        $nav[] = ['label' => __('nav.staff'), 'group' => 'system', 'href' => route('admin.staff.index'), 'icon' => 'students', 'active' => request()->routeIs('admin.staff.*')];
        $nav[] = ['label' => __('nav.roles'), 'group' => 'system', 'href' => route('admin.roles.index'), 'icon' => 'key', 'active' => request()->routeIs('admin.roles.*')];
    }

    // Sidebar sections (stable sort keeps each section's own order): overview, academic, content & communication, system.
    $groupOrder = ['main' => 0, 'academic' => 1, 'content' => 2, 'system' => 3];
    usort($nav, fn ($a, $b) => ($groupOrder[$a['group'] ?? 'system'] ?? 3) <=> ($groupOrder[$b['group'] ?? 'system'] ?? 3));
@endphp

<x-layout.app :title="$title" :heading="$heading" :nav="$nav" :context="__('nav.context_admin')" :search-route="route('admin.search.index')">
    {{ $slot }}
</x-layout.app>
