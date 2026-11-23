<script>
    import {
        X,
        User as UserIcon,
        KeyRound,
        Camera,
        Trash2,
        Eye,
        EyeOff,
        CheckCircle2,
        ShieldCheck,
        Briefcase,
        Phone,
        Mail,
        CalendarCheck
    } from '@lucide/svelte';
    import { useForm } from '@inertiajs/svelte';

    let { authUser = null, visits = [] } = $props();

    let isOpen = $state(false);
    let activeTab = $state('profile'); // 'profile' | 'security'
    let previewUrl = $state(null);
    let fileInput = $state(null);
    let profileSuccess = $state('');
    let passwordSuccess = $state('');

    let showCurrent = $state(false);
    let showNew = $state(false);
    let showConfirm = $state(false);

    const profileForm = useForm({
        name: '',
        phone: '',
        position: '',
        notes: '',
        avatar: null,
        remove_avatar: false,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    let myStats = $derived.by(() => {
        if (!authUser) return { total: 0, upcoming: 0, completed: 0 };
        const init = (authUser.initial || '').toUpperCase();
        const mine = visits.filter((v) => {
            if (v.primary_sales_user_id === authUser.id || v.created_by === authUser.id) return true;
            if (init && Array.isArray(v.support_marketing)) {
                return v.support_marketing.some((s) => String(s).toUpperCase() === init);
            }
            return false;
        });
        return {
            total: mine.length,
            upcoming: mine.filter((v) => ['Draft', 'Planned', 'Confirmed'].includes(v.status)).length,
            completed: mine.filter((v) => v.status === 'Completed').length,
        };
    });

    export function open(tab = 'profile') {
        activeTab = tab;
        profileSuccess = '';
        passwordSuccess = '';

        profileForm.name = authUser?.name || '';
        profileForm.phone = authUser?.phone || '';
        profileForm.position = authUser?.position || '';
        profileForm.notes = authUser?.notes || '';
        profileForm.avatar = null;
        profileForm.remove_avatar = false;
        profileForm.clearErrors();
        previewUrl = authUser?.avatar_url || null;

        passwordForm.reset();
        passwordForm.clearErrors();
        showCurrent = false;
        showNew = false;
        showConfirm = false;

        isOpen = true;
    }

    export function close() {
        isOpen = false;
    }

    function handleFileChange(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        profileForm.avatar = file;
        profileForm.remove_avatar = false;
        previewUrl = URL.createObjectURL(file);
    }

    function handleRemoveAvatar() {
        profileForm.avatar = null;
        profileForm.remove_avatar = true;
        previewUrl = null;
        if (fileInput) fileInput.value = '';
    }

    function submitProfile(e) {
        e.preventDefault();
        profileSuccess = '';
        profileForm.post('/profile', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                profileForm.avatar = null;
                profileForm.remove_avatar = false;
                profileSuccess = 'Profil berhasil diperbarui.';
                setTimeout(() => (profileSuccess = ''), 2500);
            },
        });
    }

    function submitPassword(e) {
        e.preventDefault();
        passwordSuccess = '';
        passwordForm.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                passwordSuccess = 'Kata sandi berhasil diperbarui.';
                setTimeout(() => (passwordSuccess = ''), 2500);
            },
        });
    }

    function getInitials(name, initial) {
        if (initial) return initial.toUpperCase();
        if (!name) return 'PV';
        return name
            .split(' ')
            .slice(0, 2)
            .map((p) => p[0])
            .join('')
            .toUpperCase();
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    {#if previewUrl}
                        <img src={previewUrl} alt={authUser?.name} class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0" />
                    {:else}
                        <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                            {getInitials(authUser?.name, authUser?.initial)}
                        </div>
                    {/if}
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 truncate">{authUser?.name || 'Profil Pengguna'}</h3>
                            {#if authUser?.initial}
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold text-slate-700">
                                    {authUser.initial}
                                </span>
                            {/if}
                        </div>
                        <p class="text-[11px] text-slate-500 truncate">{authUser?.email || ''}</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={close}
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-slate-200 px-5 bg-slate-50/70 shrink-0">
                <button
                    type="button"
                    onclick={() => (activeTab = 'profile')}
                    class="flex items-center gap-1.5 py-2.5 px-3 text-xs font-medium border-b-2 transition-colors cursor-pointer {activeTab === 'profile' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                >
                    <UserIcon class="w-3.5 h-3.5" />
                    <span>Profil & Foto</span>
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'security')}
                    class="flex items-center gap-1.5 py-2.5 px-3 text-xs font-medium border-b-2 transition-colors cursor-pointer {activeTab === 'security' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                >
                    <KeyRound class="w-3.5 h-3.5" />
                    <span>Ganti Kata Sandi</span>
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 overflow-y-auto space-y-4">
                {#if activeTab === 'profile'}
                    {#if profileSuccess}
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium">
                            <CheckCircle2 class="w-4 h-4 shrink-0" />
                            <span>{profileSuccess}</span>
                        </div>
                    {/if}

                    <!-- Mini Personal Stats -->
                    <div class="grid grid-cols-3 gap-2.5">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <p class="text-[10px] font-medium text-slate-500 uppercase">Visit Saya</p>
                            <p class="text-base font-bold text-slate-900 mt-0.5">{myStats.total}</p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-blue-50/60 border border-blue-100">
                            <p class="text-[10px] font-medium text-blue-600 uppercase">Aktif / Jadwal</p>
                            <p class="text-base font-bold text-blue-700 mt-0.5">{myStats.upcoming}</p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-100">
                            <p class="text-[10px] font-medium text-emerald-600 uppercase">Selesai</p>
                            <p class="text-base font-bold text-emerald-700 mt-0.5">{myStats.completed}</p>
                        </div>
                    </div>

                    <form onsubmit={submitProfile} class="space-y-4">
                        <!-- Avatar Uploader -->
                        <div class="flex items-center gap-4 p-3.5 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="relative shrink-0">
                                {#if previewUrl}
                                    <img src={previewUrl} alt="Preview" class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-xs" />
                                {:else}
                                    <div class="w-16 h-16 rounded-full bg-slate-900 text-white flex items-center justify-center text-base font-bold border-2 border-white shadow-xs">
                                        {getInitials(profileForm.name, authUser?.initial)}
                                    </div>
                                {/if}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-slate-800">Foto Profil</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <input
                                        bind:this={fileInput}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onchange={handleFileChange}
                                        class="hidden"
                                    />
                                    <button
                                        type="button"
                                        onclick={() => fileInput?.click()}
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-medium cursor-pointer"
                                    >
                                        <Camera class="w-3.5 h-3.5" />
                                        <span>Pilih Foto</span>
                                    </button>
                                    {#if previewUrl}
                                        <button
                                            type="button"
                                            onclick={handleRemoveAvatar}
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-medium cursor-pointer"
                                        >
                                            <Trash2 class="w-3 h-3" />
                                            <span>Hapus</span>
                                        </button>
                                    {/if}
                                </div>
                                {#if profileForm.errors.avatar}
                                    <p class="mt-1 text-[11px] text-rose-600">{profileForm.errors.avatar}</p>
                                {/if}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="prof_name" class="block text-xs font-medium text-slate-700 mb-1">Nama Lengkap *</label>
                                <input
                                    id="prof_name"
                                    type="text"
                                    bind:value={profileForm.name}
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                                />
                                {#if profileForm.errors.name}
                                    <p class="mt-1 text-[11px] text-rose-600">{profileForm.errors.name}</p>
                                {/if}
                            </div>

                            <div>
                                <label for="prof_phone" class="block text-xs font-medium text-slate-700 mb-1">No. HP / WhatsApp</label>
                                <input
                                    id="prof_phone"
                                    type="text"
                                    bind:value={profileForm.phone}
                                    placeholder="0812..."
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="prof_position" class="block text-xs font-medium text-slate-700 mb-1">Jabatan / Posisi</label>
                            <input
                                id="prof_position"
                                type="text"
                                bind:value={profileForm.position}
                                placeholder="Product Specialist / Sales Executive"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                            />
                        </div>

                        <div>
                            <label for="prof_notes" class="block text-xs font-medium text-slate-700 mb-1">Catatan / Area Fokus</label>
                            <textarea
                                id="prof_notes"
                                rows="2"
                                bind:value={profileForm.notes}
                                placeholder="Catatan singkat..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                            ></textarea>
                        </div>

                        <!-- Role & Permission Badges (Read-only) -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                            <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700">
                                <ShieldCheck class="w-3.5 h-3.5 text-slate-500" />
                                <span>Info Akses Akun</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5 text-[11px]">
                                <span class="px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-700">
                                    Role: <strong>{authUser?.role || '-'}</strong>
                                </span>
                                {#if authUser?.team}
                                    <span class="px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-700">
                                        Tim: <strong>{authUser.team}</strong>
                                    </span>
                                {/if}
                                {#if authUser?.can_view_all_visits}
                                    <span class="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-700">View All Visits</span>
                                {/if}
                                {#if authUser?.can_edit_all_visits}
                                    <span class="px-2 py-0.5 rounded bg-blue-50 border border-blue-200 text-blue-700">Edit All Visits</span>
                                {/if}
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onclick={close}
                                class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 cursor-pointer"
                            >
                                Tutup
                            </button>
                            <button
                                type="submit"
                                disabled={profileForm.processing}
                                class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-xs font-medium cursor-pointer"
                            >
                                {profileForm.processing ? 'Menyimpan...' : 'Simpan Profil'}
                            </button>
                        </div>
                    </form>
                {:else}
                    {#if passwordSuccess}
                        <div class="flex items-center gap-2 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-medium">
                            <CheckCircle2 class="w-4 h-4 shrink-0" />
                            <span>{passwordSuccess}</span>
                        </div>
                    {/if}

                    <form onsubmit={submitPassword} class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-xs font-medium text-slate-700 mb-1">
                                Kata Sandi Saat Ini
                            </label>
                            <div class="relative">
                                <input
                                    id="current_password"
                                    type={showCurrent ? 'text' : 'password'}
                                    bind:value={passwordForm.current_password}
                                    required
                                    autocomplete="current-password"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                                    placeholder="Masukkan kata sandi lama"
                                />
                                <button
                                    type="button"
                                    onclick={() => (showCurrent = !showCurrent)}
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                >
                                    {#if showCurrent}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                                </button>
                            </div>
                            {#if passwordForm.errors.current_password}
                                <p class="mt-1 text-[11px] text-rose-600">{passwordForm.errors.current_password}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="new_password" class="block text-xs font-medium text-slate-700 mb-1">
                                Kata Sandi Baru (Minimal 8 Karakter)
                            </label>
                            <div class="relative">
                                <input
                                    id="new_password"
                                    type={showNew ? 'text' : 'password'}
                                    bind:value={passwordForm.password}
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                                    placeholder="Masukkan kata sandi baru"
                                />
                                <button
                                    type="button"
                                    onclick={() => (showNew = !showNew)}
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                >
                                    {#if showNew}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                                </button>
                            </div>
                            {#if passwordForm.errors.password}
                                <p class="mt-1 text-[11px] text-rose-600">{passwordForm.errors.password}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-slate-700 mb-1">
                                Konfirmasi Kata Sandi Baru
                            </label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    type={showConfirm ? 'text' : 'password'}
                                    bind:value={passwordForm.password_confirmation}
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-9 text-xs text-slate-900 focus:border-slate-900 focus:outline-hidden"
                                    placeholder="Ulangi kata sandi baru"
                                />
                                <button
                                    type="button"
                                    onclick={() => (showConfirm = !showConfirm)}
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                >
                                    {#if showConfirm}<EyeOff class="w-3.5 h-3.5" />{:else}<Eye class="w-3.5 h-3.5" />{/if}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                onclick={close}
                                class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 cursor-pointer"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={passwordForm.processing}
                                class="px-4 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white text-xs font-medium cursor-pointer"
                            >
                                {passwordForm.processing ? 'Menyimpan...' : 'Simpan Kata Sandi'}
                            </button>
                        </div>
                    </form>
                {/if}
            </div>
        </div>
    </div>
{/if}