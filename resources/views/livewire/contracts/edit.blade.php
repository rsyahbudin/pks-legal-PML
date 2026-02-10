<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Ticket;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // Common fields
    public $division_id;

    public $department_id;

    public int $has_financial_impact = 0;

    public string $payment_type = '';

    public string $recurring_description = '';

    public string $proposed_document_title = '';

    public $draft_document;

    public string $document_type = '';

    // Conditional: perjanjian/nda
    public string $counterpart_name = '';

    public string $agreement_start_date = '';

    public string $agreement_duration = '';

    public int $is_auto_renewal = 0;

    public string $renewal_period = '';

    public string $renewal_notification_days = '';

    public string $agreement_end_date = '';

    public string $termination_notification_days = '';

    // Conditional: surat_kuasa
    public string $kuasa_pemberi = '';

    public string $kuasa_penerima = '';

    public string $kuasa_start_date = '';

    public string $kuasa_end_date = '';

    // Common for all
    public int $tat_legal_compliance = 0;

    public $mandatory_documents = [];

    public $approval_document;

    // Pre-done questions (for Perjanjian only, when status = done)
    public ?int $pre_done_question_1 = null;

    public ?int $pre_done_question_2 = null;

    public ?int $pre_done_question_3 = null;

    public string $pre_done_remarks = '';

    public function mount(int $contract): void
    {
        // Load ticket data
        $this->ticket = Ticket::findOrFail($contract);

        // Populate form with ticket data
        $this->division_id = $this->ticket->division_id;
        $this->department_id = $this->ticket->department_id;
        $this->has_financial_impact = $this->ticket->has_financial_impact;
        $this->payment_type = $this->ticket->payment_type ?? '';
        $this->recurring_description = $this->ticket->recurring_description ?? '';
        $this->proposed_document_title = $this->ticket->proposed_document_title;
        $this->document_type = $this->ticket->documentType?->code ?? '';
        $this->tat_legal_compliance = $this->ticket->tat_legal_compliance;

        // Conditional fields
        $this->counterpart_name = $this->ticket->counterpart_name ?? '';
        $this->agreement_start_date = $this->ticket->agreement_start_date?->format('Y-m-d') ?? '';
        $this->agreement_duration = $this->ticket->agreement_duration ?? '';
        $this->is_auto_renewal = $this->ticket->is_auto_renewal;
        $this->renewal_period = $this->ticket->renewal_period ?? '';
        $this->renewal_notification_days = $this->ticket->renewal_notification_days ?? '';
        $this->agreement_end_date = $this->ticket->agreement_end_date?->format('Y-m-d') ?? '';
        $this->termination_notification_days = $this->ticket->termination_notification_days ?? '';

        $this->kuasa_pemberi = $this->ticket->kuasa_pemberi ?? '';
        $this->kuasa_penerima = $this->ticket->kuasa_penerima ?? '';
        $this->kuasa_start_date = $this->ticket->kuasa_start_date?->format('Y-m-d') ?? '';
        $this->kuasa_end_date = $this->ticket->kuasa_end_date?->format('Y-m-d') ?? '';

        // Pre-done questions - convert to integer for radio button compatibility
        $this->pre_done_question_1 = $this->ticket->pre_done_question_1 !== null ? (int) $this->ticket->pre_done_question_1 : null;
        $this->pre_done_question_2 = $this->ticket->pre_done_question_2 !== null ? (int) $this->ticket->pre_done_question_2 : null;
        $this->pre_done_question_3 = $this->ticket->pre_done_question_3 !== null ? (int) $this->ticket->pre_done_question_3 : null;
        $this->pre_done_remarks = $this->ticket->pre_done_remarks ?? '';
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('name')->get();
    }

    public function getDepartmentsProperty()
    {
        if (! $this->division_id) {
            return collect();
        }

        return Department::where('division_id', $this->division_id)->orderBy('name')->get();
    }

    public function save(): void
    {
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only legal team can edit tickets.');

            return;
        }

        // Base validation rules (files are now optional for edit)
        $rules = [
            'has_financial_impact' => ['required', 'boolean'],
            'proposed_document_title' => ['required', 'string', 'max:255'],
            'draft_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'document_type' => ['required', Rule::in(['perjanjian', 'nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'surat_lainnya'])],
            'tat_legal_compliance' => ['required', 'boolean'],
            'mandatory_documents.*' => ['nullable', 'file', 'max:10240'],
            'approval_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        // Conditional validation based on document_type
        if (in_array($this->document_type, ['perjanjian', 'nda'])) {
            $rules['counterpart_name'] = ['required', 'string', 'max:255'];
            $rules['agreement_start_date'] = ['required', 'date'];
            $rules['agreement_duration'] = ['required', 'string', 'max:100'];
            $rules['is_auto_renewal'] = ['required', 'boolean'];

            if ($this->is_auto_renewal) {
                $rules['renewal_period'] = ['required', Rule::in(['yearly', 'monthly', 'weekly'])];
                $rules['renewal_notification_days'] = ['required', 'integer', 'min:1'];
            } else {
                $rules['agreement_end_date'] = ['required', 'date', 'after:agreement_start_date'];
            }

            $rules['termination_notification_days'] = ['nullable', 'integer', 'min:1'];
        } elseif ($this->document_type === 'surat_kuasa') {
            $rules['kuasa_pemberi'] = ['required', 'string', 'max:255'];
            $rules['kuasa_penerima'] = ['required', 'string', 'max:255'];
            $rules['kuasa_start_date'] = ['required', 'date'];
            $rules['kuasa_end_date'] = ['required', 'date', 'after:kuasa_start_date'];
        }

        // Pre-done questions validation (for Perjanjian when status = done)
        if ($this->document_type === 'perjanjian' && $this->ticket->status?->code === 'done') {
            $rules['pre_done_question_1'] = ['required', 'boolean'];
            $rules['pre_done_question_2'] = ['required', 'boolean'];
            $rules['pre_done_question_3'] = ['required', 'boolean'];
            $rules['pre_done_remarks'] = ['nullable', 'string', 'max:1000'];
        }

        $validated = $this->validate($rules);

        // Update ticket
        $this->ticket->update([
            'division_id' => $this->division_id,
            'department_id' => $this->department_id,
            'has_financial_impact' => $validated['has_financial_impact'],
            'proposed_document_title' => $validated['proposed_document_title'],
            'document_type_id' => \App\Models\DocumentType::getIdByCode($validated['document_type']),
            // Conditional fields
            'counterpart_name' => $this->counterpart_name ?: null,
            'agreement_start_date' => $this->agreement_start_date ?: null,
            'agreement_duration' => $this->agreement_duration ?: null,
            'is_auto_renewal' => $this->is_auto_renewal,
            'renewal_period' => $this->is_auto_renewal ? ($this->renewal_period ?: null) : null,
            'renewal_notification_days' => $this->is_auto_renewal ? ($this->renewal_notification_days ?: null) : null,
            'agreement_end_date' => (! $this->is_auto_renewal && $this->agreement_end_date) ? $this->agreement_end_date : null,
            'termination_notification_days' => $this->termination_notification_days ?: null,
            'kuasa_pemberi' => $this->kuasa_pemberi ?: null,
            'kuasa_penerima' => $this->kuasa_penerima ?: null,
            'kuasa_start_date' => $this->kuasa_start_date ?: null,
            'kuasa_end_date' => $this->kuasa_end_date ?: null,
            'tat_legal_compliance' => $validated['tat_legal_compliance'],
            // Pre-done questions (if applicable)
            'pre_done_question_1' => $this->pre_done_question_1,
            'pre_done_question_2' => $this->pre_done_question_2,
            'pre_done_question_3' => $this->pre_done_question_3,
            'pre_done_remarks' => $this->pre_done_remarks,
        ]);

        // Handle file uploads (only if new files uploaded)
        if ($this->draft_document) {
            $draftPath = $this->draft_document->store("tickets/{$this->ticket->id}/draft", 'public');
            $this->ticket->update(['draft_document_path' => $draftPath]);
        }

        if ($this->mandatory_documents && count($this->mandatory_documents) > 0) {
            // Append to existing documents
            $existingDocs = $this->ticket->mandatory_documents_path ?? [];
            $mandatoryPaths = $existingDocs;

            foreach ($this->mandatory_documents as $file) {
                $path = $file->store("tickets/{$this->ticket->id}/mandatory", 'public');
                $mandatoryPaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                ];
            }
            $this->ticket->update(['mandatory_documents_path' => $mandatoryPaths]);
        }

        if ($this->approval_document) {
            $approvalPath = $this->approval_document->store("tickets/{$this->ticket->id}/approval", 'public');
            $this->ticket->update(['approval_document_path' => $approvalPath]);
        }

        // Log activity
        $logMessage = 'updated ticket details';
        if ($this->document_type === 'perjanjian' && $this->ticket->status?->code === 'done') {
            $logMessage .= ' (including pre-done answers)';
        }

        $this->ticket->activityLogs()->create([
            'user_id' => $user->id,
            'action' => $logMessage,
            'metadata' => [
                'updated_at' => now(),
                'updated_by' => $user->name,
            ],
        ]);

        session()->flash('success', 'Ticket updated successfully.');
        $this->redirect(route('tickets.show', $this->ticket->id), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Back to List
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Edit Ticket</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Update ticket information. File upload is optional (only if you want to replace documents).</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        <!-- Informasi Dasar -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">1. Basic Information</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Division (readonly, auto-filled) -->
                <flux:field>
                    <flux:label>User Directorate (Division)</flux:label>
                    <flux:select wire:model="division_id" disabled>
                        @foreach($this->divisions as $division)
                        <option value="{{ $division->id }}" {{ $division->id == $this->division_id ? 'selected' : '' }}>{{ $division->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Auto-filled from your account</flux:description>
                </flux:field>

                <!-- Department (readonly, auto-filled) -->
                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="department_id" disabled>
                        <option value="">-</option>
                        @foreach($this->departments as $dept)
                        <option value="{{ $dept->id }}" {{ $dept->id == $this->department_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Auto-filled from your account</flux:description>
                </flux:field>

                <!-- Financial Impact -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Financial Impact (Income/Expenditure) *</flux:label>
                    <flux:radio.group wire:model.live="has_financial_impact" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="has_financial_impact" />
                </flux:field>

                <!-- Payment Type (conditional) -->
                @if($has_financial_impact)
                <flux:field class="sm:col-span-2">
                    <flux:label>Payment Type *</flux:label>
                    <flux:radio.group wire:model.live="payment_type" variant="segmented" required>
                        <flux:radio value="pay" label="Pay" />
                        <flux:radio value="receive_payment" label="Receive Payment" />
                    </flux:radio.group>
                    <flux:error name="payment_type" />
                </flux:field>
                @endif

                <!-- Recurring Description (conditional on payment_type = 'pay') -->
                @if($has_financial_impact && $payment_type === 'pay')
                <flux:field class="sm:col-span-2">
                    <flux:label>Recurring Description (Optional)</flux:label>
                    <flux:input 
                        wire:model="recurring_description" 
                        placeholder="Example: Monthly, Every 3 months, etc"
                    />
                    <flux:error name="recurring_description" />
                </flux:field>
                @endif

                <!-- Usulan Judul Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Proposed Document Title *</flux:label>
                    <flux:input wire:model="proposed_document_title" placeholder="Enter proposed document title" required />
                    <flux:error name="proposed_document_title" />
                </flux:field>

                <!-- Draft Usulan Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Draft Document (Optional)</flux:label>
                    <input type="file" wire:model="draft_document" accept=".pdf,.doc,.docx" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                    <flux:description>PDF or Word, max 10MB</flux:description>
                    <flux:error name="draft_document" />
                    <div wire:loading wire:target="draft_document" class="mt-2 text-sm text-blue-600">
                        Uploading draft...
                    </div>
                </flux:field>

                <!-- Jenis Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Document Type *</flux:label>
                    <flux:select wire:model.live="document_type" required>
                        <option value="">Select Document Type</option>
                        <option value="perjanjian">Agreement/Addendum/Amendment</option>
                        <option value="nda">Non-Disclosure Agreement (NDA)</option>
                        <option value="surat_kuasa">Power of Attorney (Surat Kuasa)</option>
                        <option value="pendapat_hukum">Legal Opinion (Pendapat Hukum)</option>
                        <option value="surat_pernyataan">Statement Letter (Surat Pernyataan)</option>
                        <option value="surat_lainnya">Other Letter (Surat Lainnya)</option>
                    </flux:select>
                    <flux:error name="document_type" />
                </flux:field>
            </div>
        </div>

        <!-- Conditional Fields: Perjanjian/NDA -->
        @if(in_array($this->document_type, ['perjanjian', 'nda']))
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">2. {{ $this->document_type === 'nda' ? 'NDA' : 'Agreement' }} Details</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>Counterpart / Other Party Name *</flux:label>
                    <flux:input wire:model="counterpart_name" placeholder="Name of other party" required />
                    <flux:error name="counterpart_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Estimated Start Date of {{ $this->document_type === 'nda' ? 'NDA' : 'Agreement' }} *</flux:label>
                    <flux:input type="date" wire:model="agreement_start_date" required />
                    <flux:error name="agreement_start_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Duration of {{ $this->document_type === 'nda' ? 'NDA' : 'Agreement' }} *</flux:label>
                    <flux:input wire:model="agreement_duration" placeholder="Example: 2 years, 12 months" required />
                    <flux:error name="agreement_duration" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Auto Renewal *</flux:label>
                    <flux:radio.group wire:model.live="is_auto_renewal" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="is_auto_renewal" />
                </flux:field>

                @if($this->is_auto_renewal)
                <flux:field>
                    <flux:label>Auto Renewal Period *</flux:label>
                    <flux:select wire:model="renewal_period" required>
                        <option value="">Select Period</option>
                        <option value="yearly">Yearly</option>
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                    </flux:select>
                    <flux:error name="renewal_period" />
                </flux:field>

                <flux:field>
                    <flux:label>Notification Period Before Renewal (Days) *</flux:label>
                    <flux:input type="number" wire:model="renewal_notification_days" placeholder="Example: 30" required />
                    <flux:error name="renewal_notification_days" />
                </flux:field>
                @endif

                @if(!$this->is_auto_renewal)
                <flux:field class="sm:col-span-2">
                    <flux:label>End Date of {{ $this->document_type === 'nda' ? 'NDA' : 'Agreement' }} *</flux:label>
                    <flux:input type="date" wire:model="agreement_end_date" required />
                    <flux:error name="agreement_end_date" />
                </flux:field>
                @endif

                <flux:field class="sm:col-span-2">
                    <flux:label>Notification Period Before Termination (Days)</flux:label>
                    <flux:input type="number" wire:model="termination_notification_days" placeholder="Example: 60" />
                    <flux:description>Optional - system will send notification before end date</flux:description>
                    <flux:error name="termination_notification_days" />
                </flux:field>
            </div>
        </div>
        @endif

        <!-- Conditional Fields: Surat Kuasa -->
        @if($this->document_type === 'surat_kuasa')
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">2. Power of Attorney Details</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Grantor (Pemberi Kuasa) *</flux:label>
                    <flux:input wire:model="kuasa_pemberi" placeholder="Name of grantor" required />
                    <flux:error name="kuasa_pemberi" />
                </flux:field>

                <flux:field>
                    <flux:label>Grantee (Penerima Kuasa) *</flux:label>
                    <flux:input wire:model="kuasa_penerima" placeholder="Name of grantee" required />
                    <flux:error name="kuasa_penerima" />
                </flux:field>

                <flux:field>
                    <flux:label>Estimated Start Date of Power of Attorney *</flux:label>
                    <flux:input type="date" wire:model="kuasa_start_date" required />
                    <flux:error name="kuasa_start_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Power of Attorney End Date *</flux:label>
                    <flux:input type="date" wire:model="kuasa_end_date" required />
                    <flux:error name="kuasa_end_date" />
                </flux:field>
            </div>
        </div>
        @endif

        <!-- Common Fields for All Types -->
        @if($this->document_type)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">{{ in_array($this->document_type, ['perjanjian', 'nda']) || $this->document_type === 'surat_kuasa' ? '3' : '2' }}. Supporting Documents</h2>
            
            <div class="grid gap-4">
                <flux:field>
                    <flux:label>Legal Turn-Around-Time Compliance *</flux:label>
                    <flux:radio.group wire:model="tat_legal_compliance" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="tat_legal_compliance" />
                </flux:field>

                <flux:field>
                    <flux:label>Mandatory Documents *</flux:label>
                    <input type="file" wire:model="mandatory_documents" multiple class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-purple-700 hover:file:bg-purple-100 dark:text-neutral-400 dark:file:bg-purple-900/30 dark:file:text-purple-400" />
                    <flux:description>Deed of Incorporation, Board of Directors Composition, Last Amendment, ID Card, etc. (Max 10MB per file, multiple uploads allowed)</flux:description>
                    <flux:error name="mandatory_documents" />
                    <div wire:loading wire:target="mandatory_documents" class="mt-2 text-sm text-purple-600">
                        Uploading mandatory documents...
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Legal Request Permit/Approval from related Head or Leader *</flux:label>
                    <input type="file" wire:model="approval_document" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-orange-700 hover:file:bg-orange-100 dark:text-neutral-400 dark:file:bg-orange-900/30 dark:file:text-orange-400" />
                    <flux:description>Screenshot of email/correspondence approval (PDF or image, max 5MB)</flux:description>
                    <flux:error name="approval_document" />
                    <div wire:loading wire:target="approval_document" class="mt-2 text-sm text-orange-600">
                        Uploading approval document...
                    </div>
                </flux:field>
            </div>
        </div>
        @endif

        {{-- Pre-Done Questions Section (for Perjanjian with done status only) --}}
        @if($this->document_type === 'perjanjian' && $ticket->status?->code === 'done')
        <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/30">
            <h2 class="mb-4 text-lg font-semibold text-green-900 dark:text-green-300">Finalization Checklist</h2>
            <p class="mb-4 text-sm text-green-700 dark:text-green-400">Answers to finalization questions. You can update them if needed.</p>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>1. Has the document been signed by both parties? *</flux:label>
                    <flux:radio.group wire:model="pre_done_question_1" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="pre_done_question_1" />
                </flux:field>

                <flux:field>
                    <flux:label>2. Has the final document been saved in the internal sharing folder? *</flux:label>
                    <flux:radio.group wire:model="pre_done_question_2" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="pre_done_question_2" />
                </flux:field>

                <flux:field>
                    <flux:label>3. Are all mandatory attachments complete? *</flux:label>
                    <flux:radio.group wire:model="pre_done_question_3" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="pre_done_question_3" />
                </flux:field>

                <flux:field>
                    <flux:label>Remarks (Optional)</flux:label>
                    <flux:textarea wire:model="pre_done_remarks" rows="3" placeholder="Additional notes or remarks (max 1000 characters)" />
                    <flux:description>Maximum 1000 characters</flux:description>
                    <flux:error name="pre_done_remarks" />
                </flux:field>
            </div>
        </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tickets.index') }}" wire:navigate>
                <flux:button variant="ghost">Cancel</flux:button>
            </a>
            <flux:button type="submit" variant="primary">
                Update Ticket
            </flux:button>
        </div>
    </form>
</div>
