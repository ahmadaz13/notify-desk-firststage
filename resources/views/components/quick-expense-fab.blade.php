@php
    $fabCategories = \App\Models\ExpenseCategory::active()->get();
    $defaultCategoryId = $fabCategories->first()?->id ?? 1;
@endphp

<div x-data="{
    open: false,
    amount: '',
    categoryId: {{ $defaultCategoryId }},
    description: '',
    date: '{{ now()->toDateString() }}',
    time: '{{ now()->format('H:i') }}',
    visibility: 'shared',
    isSubmitting: false,
    showToast: false,
    toastMessage: '',
    openModal() {
        this.open = true;
        this.$nextTick(() => {
            if (this.$refs.amountInput) {
                this.$refs.amountInput.focus();
            }
        });
    },
    closeModal() {
        this.open = false;
        this.amount = '';
        this.description = '';
    },
    async submitExpense() {
        if (!this.amount || parseFloat(this.amount) <= 0) {
            alert('يرجى إدخال مبلغ المصروف بشكل صحيح.');
            return;
        }
        this.isSubmitting = true;
        try {
            const res = await fetch('{{ route('expenses.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    amount: parseFloat(this.amount),
                    category_id: this.categoryId,
                    description: this.description,
                    date: this.date,
                    time: this.time,
                    visibility: this.visibility,
                    frequency: 'one_time'
                })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                this.toastMessage = 'تم تسجيل المصروف بنجاح ✓';
                this.showToast = true;
                this.closeModal();
                setTimeout(() => {
                    this.showToast = false;
                    // Reload if on dashboard to show updated metrics
                    if (window.location.pathname === '/' || window.location.pathname === '') {
                        window.location.reload();
                    }
                }, 1200);
            } else {
                alert(data.message || 'حدث خطأ أثناء حفظ المصروف.');
            }
        } catch (err) {
            alert('تعذر الاتصال بالخادم، يرجى المحاولة ثانية.');
        } finally {
            this.isSubmitting = false;
        }
    }
}">
    {{-- Floating Action Button (FAB) --}}
    <button
        type="button"
        id="quick-expense-fab-btn"
        aria-label="تسجيل مصروف سريع (أقل من 10 ثوانٍ)"
        @click="openModal()"
        title="تسجيل مصروف سريع (أقل من 10 ثوانٍ)"
        style="position:fixed;bottom:calc(82px + env(safe-area-inset-bottom, 0px));inset-inline-start:20px;z-index:99;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;min-height:48px;border-radius:50px;background:linear-gradient(135deg,var(--nd-primary),#0284c7);color:#fff;border:none;box-shadow:0 8px 24px rgba(12,134,237,0.35);font-size:15px;font-weight:800;cursor:pointer;transition:transform 0.15s ease,box-shadow 0.15s ease;"
        onmouseover="this.style.transform='scale(1.05)'"
        onmouseout="this.style.transform='scale(1)'"
    >
        <span style="font-size:18px;line-height:1">⚡</span>
        <span>＋ مصروف سريع</span>
    </button>

    {{-- Toast Notification --}}
    <div
        x-show="showToast"
        x-cloak
        style="position:fixed;bottom:140px;left:50%;transform:translateX(-50%);z-index:101;background:#0f172a;color:#fff;padding:12px 24px;border-radius:12px;font-weight:700;font-size:14px;box-shadow:0 10px 30px rgba(0,0,0,0.3);display:flex;align-items:center;gap:10px;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
    >
        <span style="color:#34d399;font-size:18px">✓</span>
        <span x-text="toastMessage"></span>
    </div>

    {{-- Quick Expense Modal --}}
    <div
        x-show="open"
        x-cloak
        class="modal-backdrop"
        style="position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;"
        @keydown.escape.window="closeModal()"
    >
        <div
            class="modal-box"
            style="background:#fff;border-radius:20px;width:100%;max-width:440px;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.25);max-height:90vh;overflow-y:auto;"
            @click.away="closeModal()"
        >
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #f1f5f9;padding-bottom:12px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:22px">⚡</span>
                    <div>
                        <h3 style="margin:0;font-size:18px;font-weight:800;color:var(--nd-ink)">تسجيل مصروف ميداني سريع</h3>
                        <small class="muted" style="font-size:12px">سجل المصروف في أقل من 10 ثوانٍ</small>
                    </div>
                </div>
                <button type="button" @click="closeModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#94a3b8;line-height:1">&times;</button>
            </div>

            <form @submit.prevent="submitExpense()">
                {{-- Amount (Large Input) --}}
                <div style="margin-bottom:18px;text-align:center">
                    <label style="display:block;font-size:13px;font-weight:700;color:#64748b;margin-bottom:6px">مبلغ المصروف (د.أ) *</label>
                    <div style="position:relative;display:inline-block;width:100%">
                        <input
                            x-ref="amountInput"
                            type="number"
                            step="0.01"
                            min="0.01"
                            x-model="amount"
                            required
                            placeholder="0.00"
                            style="width:100%;font-size:28px;font-weight:800;text-align:center;padding:12px 16px;border-radius:14px;border:2px solid var(--nd-primary);background:#f0f9ff;color:var(--nd-ink);outline:none;box-sizing:border-box"
                        >
                    </div>
                </div>

                {{-- Category Buttons (6 Icons) --}}
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:13px;font-weight:700;color:var(--nd-ink);margin-bottom:8px">التصنيف *</label>
                    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px">
                        @foreach($fabCategories as $cat)
                            <button
                                type="button"
                                @click="categoryId = {{ $cat->id }}"
                                :style="categoryId === {{ $cat->id }}
                                    ? 'background:{{ $cat->color }}15;border-color:{{ $cat->color }};box-shadow:0 0 0 2px {{ $cat->color }}'
                                    : 'background:#f8fafc;border-color:#e2e8f0'"
                                style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;border-radius:12px;border:1.5px solid #e2e8f0;cursor:pointer;transition:all 0.15s ease"
                            >
                                <span style="font-size:20px">{{ $cat->icon }}</span>
                                <span style="font-size:12px;font-weight:700;color:var(--nd-ink)">{{ $cat->name }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Description (Single Line) --}}
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:13px;font-weight:700;color:var(--nd-ink);margin-bottom:6px">الوصف / ملاحظة (اختياري)</label>
                    <input
                        type="text"
                        x-model="description"
                        placeholder="مثال: بنزين سيارة أحمد، قهوة مع عميل..."
                        style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid #cbd5e1;font-size:14px;box-sizing:border-box"
                    >
                </div>

                {{-- Visibility Toggle --}}
                <div style="margin-bottom:16px;background:#f8fafc;padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0">
                    <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px">مستوى الرؤية</label>
                    <div style="display:flex;gap:8px">
                        <button
                            type="button"
                            @click="visibility = 'shared'"
                            :style="visibility === 'shared' ? 'background:var(--nd-primary);color:#fff;border-color:var(--nd-primary)' : 'background:#fff;color:var(--nd-ink);border-color:#cbd5e1'"
                            style="flex:1;padding:8px 10px;border-radius:8px;border:1px solid;font-size:13px;font-weight:700;cursor:pointer"
                        >
                            👥 مشترك (أحمد وخالد)
                        </button>
                        <button
                            type="button"
                            @click="visibility = 'personal'"
                            :style="visibility === 'personal' ? 'background:#64748b;color:#fff;border-color:#64748b' : 'background:#fff;color:var(--nd-ink);border-color:#cbd5e1'"
                            style="flex:1;padding:8px 10px;border-radius:8px;border:1px solid;font-size:13px;font-weight:700;cursor:pointer"
                        >
                            🔒 شخصي خاص بي
                        </button>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div style="display:flex;gap:10px;margin-top:20px">
                    <button
                        type="button"
                        @click="closeModal()"
                        class="btn btn-ghost"
                        style="flex:1;min-height:48px;border:1px solid #cbd5e1"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="btn btn-primary"
                        style="flex:2;min-height:48px;font-size:15px;font-weight:800;background:var(--nd-primary);display:flex;align-items:center;justify-content:center;gap:8px"
                    >
                        <span x-show="!isSubmitting">✓ حفظ المصروف</span>
                        <span x-show="isSubmitting">جارٍ الحفظ...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
