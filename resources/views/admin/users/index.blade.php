<x-admin-layout>
    <x-slot name="title">
        إدارة العملاء
    </x-slot>

    <div class="py-6" x-data="{ openPasswordModal: null }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100">
                <div class="p-8 bg-white">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h2 class="text-2xl font-black text-brand-blue-800 tracking-tight flex items-center gap-3">
                                <span class="bg-brand-blue/10 p-2 rounded-xl text-brand-blue">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                </span>
                                إدارة العملاء
                            </h2>
                            <p class="text-sm text-gray-500 font-medium mt-1">عرض وإدارة حسابات العملاء المسجلين من التطبيق والويب.</p>
                        </div>
                        <div class="w-full md:w-auto">
                             <form action="{{ route('admin.users.index') }}" method="GET" class="relative group">
                                <input type="text" name="search" placeholder="بحث بالاسم، البريد أو الهاتف..." 
                                    class="w-full md:w-80 bg-slate-50 border-gray-200 rounded-xl py-3 pr-11 pl-4 text-sm focus:ring-2 focus:ring-brand-blue/20 focus:bg-white transition-all border shadow-sm" 
                                    value="{{ request('search') }}">
                                <svg class="w-5 h-5 text-gray-400 absolute right-4 top-3.5 group-focus-within:text-brand-blue transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </form>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-gray-200 shadow-sm bg-white">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-brand-blue-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-brand-blue-800 uppercase tracking-widest italic">#</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-brand-blue-800 uppercase tracking-wider">العميل</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-brand-blue-800 uppercase tracking-wider">كلمة السر</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-brand-blue-800 uppercase tracking-wider">رقم الهاتف</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-brand-blue-800 uppercase tracking-wider">البصمة</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-brand-blue-800 uppercase tracking-wider">الحالة</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-brand-blue-800 uppercase tracking-wider">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse ($users as $user)
                                    <tr class="hover:bg-brand-blue/5 transition-all duration-200 group">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-black text-brand-blue/40">
                                            {{ $user->id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-11 w-11 flex-shrink-0 relative">
                                                    <img class="h-11 w-11 rounded-2xl object-cover shadow-sm ring-2 ring-white bg-white border border-gray-100" 
                                                         src="{{ $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=1e3a8a&color=ffffff&bold=true' }}" 
                                                         alt="">
                                                    @if($user->email_verified_at)
                                                        <span class="absolute -top-1 -right-1 bg-green-500 border-2 border-white w-4 h-4 rounded-full flex items-center justify-center shadow-sm" title="حساب موثق">
                                                            <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="mr-4">
                                                    <div class="text-sm font-bold text-gray-900 leading-tight">{{ $user->name }}</div>
                                                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                        {{ $user->email }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button @click="openPasswordModal = {{ $user->id }}" 
                                                class="text-[11px] font-bold px-4 py-2 bg-slate-100 text-slate-600 rounded-xl hover:bg-brand-blue hover:text-white hover:shadow-lg hover:shadow-brand-blue/20 transition-all active:scale-95">
                                                تغيير السر
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-bold text-gray-700 tracking-wider flex items-center gap-1.5">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                                {{ $user->phone ?? 'لا يوجد' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            @if($user->has_biometric)
                                            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-teal-50 text-teal-700 border border-teal-100 text-[11px] font-black shadow-sm">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                مفعلة
                                            </div>
                                            @else
                                            <span class="text-gray-300 text-[11px] font-bold italic">-- غير مفعلة --</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <form action="{{ route('admin.users.toggleStatus', $user) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-4 py-1.5 rounded-full text-[11px] font-black uppercase tracking-widest transition-all shadow-sm {{ $user->status === 'active' ? 'bg-green-100 text-green-700 border border-green-200 hover:bg-green-500 hover:text-white' : 'bg-red-100 text-red-700 border border-red-200 hover:bg-red-600 hover:text-white' }}">
                                                    {{ $user->status === 'active' ? 'نشط' : 'محظور' }}
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex justify-center items-center gap-1">
                                                <!-- Send OTP -->
                                                <form action="{{ route('admin.users.sendOtp', $user) }}" method="POST" class="inline" title="إرسال كود تحقق لهاتفه">
                                                    @csrf
                                                    <button type="submit" class="w-10 h-10 flex items-center justify-center text-brand-blue hover:bg-brand-blue/10 hover:text-brand-blue-700 rounded-xl transition-all active:scale-90">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                                    </button>
                                                </form>

                                                <!-- Confirm Account -->
                                                @if(!$user->email_verified_at)
                                                <form action="{{ route('admin.users.verifyAccount', $user) }}" method="POST" class="inline" title="تفعيل/تأكيد الحساب يدوياً">
                                                    @csrf
                                                    <button type="submit" class="w-10 h-10 flex items-center justify-center text-green-600 hover:bg-green-50 rounded-xl transition-all active:scale-90 border border-green-100">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    </button>
                                                </form>
                                                @endif

                                                <a href="{{ route('admin.users.edit', $user->id) }}" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-brand-blue hover:bg-slate-50 rounded-xl transition-all">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Password Modal -->
                                    <template x-if="openPasswordModal === {{ $user->id }}">
                                        <div class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-md transition-all">
                                            <div @click.away="openPasswordModal = null" class="bg-white rounded-[32px] w-full max-w-md shadow-2xl overflow-hidden p-10 animate-in zoom-in duration-300 ring-1 ring-black/5">
                                                <div class="flex justify-between items-center mb-8">
                                                    <div class="bg-brand-blue/5 p-3 rounded-2xl">
                                                        <svg class="w-7 h-7 text-brand-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                                    </div>
                                                    <button @click="openPasswordModal = null" class="w-10 h-10 flex items-center justify-center bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 rounded-full transition-all"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                                                </div>
                                                <h3 class="text-2xl font-black text-brand-blue-900 mb-2">تعيين كلمة سر جديدة</h3>
                                                <p class="text-sm text-gray-500 mb-10 leading-relaxed font-medium">أنت بصدد تغيير كلمة سر حساب العميل: <span class="font-black text-brand-blue underline decoration-brand-blue/30">{{ $user->name }}</span>.</p>
                                                
                                                <form action="{{ route('admin.users.resetPassword', $user) }}" method="POST" class="space-y-8">
                                                    @csrf
                                                    <div class="space-y-3">
                                                        <label class="block text-xs font-black text-gray-700 uppercase tracking-widest mr-1">كلمة السر الجديدة:</label>
                                                        <input type="password" name="password" required placeholder="••••••••" class="w-full rounded-2xl border-gray-100 bg-gray-50 focus:ring-4 focus:ring-brand-blue/10 focus:border-brand-blue focus:bg-white transition-all py-4 px-6 text-center text-lg font-mono">
                                                    </div>
                                                    <div class="space-y-3">
                                                        <label class="block text-xs font-black text-gray-700 uppercase tracking-widest mr-1">تأكيد كلمة السر:</label>
                                                        <input type="password" name="password_confirmation" required placeholder="••••••••" class="w-full rounded-2xl border-gray-100 bg-gray-50 focus:ring-4 focus:ring-brand-blue/10 focus:border-brand-blue focus:bg-white transition-all py-4 px-6 text-center text-lg font-mono">
                                                    </div>
                                                    <div class="flex gap-4 pt-4">
                                                        <button type="submit" class="flex-[2] bg-brand-blue text-white font-black py-5 rounded-2xl shadow-xl shadow-brand-blue/20 hover:bg-brand-blue-700 hover:-translate-y-1 transition-all active:scale-95 text-lg">حفظ التغييرات</button>
                                                        <button type="button" @click="openPasswordModal = null" class="flex-1 bg-gray-100 text-gray-600 font-bold py-5 rounded-2xl hover:bg-gray-200 transition-all text-lg">إلغاء</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-32 text-center bg-brand-blue-50/20">
                                            <div class="max-w-xs mx-auto">
                                                <div class="bg-white w-24 h-24 rounded-3xl shadow-sm border border-gray-100 mx-auto flex items-center justify-center mb-6">
                                                    <svg class="w-12 h-12 text-brand-blue/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                                </div>
                                                <h3 class="text-xl font-black text-brand-blue-900 mb-2">لا يوجد عملاء</h3>
                                                <p class="text-sm text-gray-500 font-medium leading-relaxed">لم يتم العثور على أي عملاء مسجلين يطابقون معايير البحث الخاصة بك حالياً.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>


                    <div class="mt-10">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>