{{-- Cover-letter generator modal: paste a JD, pick a tone, run AI,
     edit inline, regenerate sections, save / export PDF / copy. All
     wiring lives on the parent resumeEditor() Alpine component
     (openCoverLetter, runCoverLetter, regenCoverSection, …). Saved
     letters per resume are listed in the right-side history rail. --}}
<template x-if="coverLetterOpen">
    <div class="resume-import-overlay" @click.self="closeCoverLetter()">
        <div class="resume-import-modal resume-import-modal-wide">
            <div class="resume-import-head">
                <h3>
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Generate cover letter</span>
                </h3>
                <button type="button" class="resume-import-close" @click="closeCoverLetter()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="resume-import-body" style="display:grid; grid-template-columns: 1fr 280px; gap: 18px;">
                {{-- Main column ───────────────────────────────────────── --}}
                <div style="min-width:0;">
                    {{-- Step 1: paste JD + pick tone ──────────────────── --}}
                    <template x-if="coverStep === 'pick'">
                        <div>
                            <p class="resume-import-help">
                                Paste the job description, pick a tone, and we'll write a tailored cover
                                letter using your resume and your saved AI voice. You can edit every line
                                before exporting.
                            </p>
                            <textarea class="resume-textarea" rows="9" maxlength="20000"
                                      placeholder="Paste the full job description here…"
                                      x-model="coverJd"
                                      @input.debounce.500ms="refreshCoverEstimate()"></textarea>

                            <div style="margin-top: 12px;">
                                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted,#9ca3af); margin-bottom:6px;">Tone</label>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <template x-for="opt in coverTones" :key="opt.value">
                                        <button type="button" class="resume-icon-btn"
                                                style="width:auto; padding: 6px 12px; font-size: 11px;"
                                                :class="coverTone === opt.value ? 'pdf-size-active' : ''"
                                                @click="coverTone = opt.value; refreshCoverEstimate()"
                                                x-text="opt.label"></button>
                                    </template>
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted,#9ca3af); margin-top: 6px;"
                                     x-text="coverToneHint()"></div>
                            </div>

                            {{-- Voice picker: pick which saved AI persona styles
                                 the letter, or "None" for resume-only voice. --}}
                            <div style="margin-top: 12px;">
                                <label style="display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted,#9ca3af); margin-bottom:6px;">Voice</label>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="button" class="resume-icon-btn"
                                            style="width:auto; padding: 6px 12px; font-size: 11px;"
                                            :class="!coverPersonaId ? 'pdf-size-active' : ''"
                                            @click="coverPersonaId = null; refreshCoverEstimate()">
                                        None
                                    </button>
                                    <template x-for="p in coverPersonas" :key="p.id">
                                        <button type="button" class="resume-icon-btn"
                                                style="width:auto; padding: 6px 12px; font-size: 11px;"
                                                :class="coverPersonaId === p.id ? 'pdf-size-active' : ''"
                                                @click="coverPersonaId = p.id; refreshCoverEstimate()"
                                                x-text="p.name"></button>
                                    </template>
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted,#9ca3af); margin-top: 6px;"
                                     x-show="!coverPersonas.length">
                                    No saved AI personas yet. Create one in <em>AI · Persona</em> to use it as a voice.
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted,#9ca3af); margin-top: 6px;"
                                     x-show="coverPersonas.length"
                                     x-text="coverPersonaId ? 'Letter will be styled in this saved voice.' : 'No voice — uses your resume and tone only.'"></div>
                            </div>

                            <div class="resume-tailor-cost">
                                <div>
                                    <i class="fas fa-coins"></i>
                                    <span x-show="coverEstimate === null && coverJd.trim().length >= 30">Estimating…</span>
                                    <span x-show="coverEstimate === null && coverJd.trim().length < 30">
                                        Paste at least 30 characters to see the cost.
                                    </span>
                                    <span x-show="coverEstimate !== null">
                                        Up to
                                        <strong x-text="coverEstimate"></strong>
                                        <span x-text="coverEstimate === 1 ? 'credit' : 'credits'"></span>
                                        · Balance:
                                        <strong x-text="coverBalance"></strong>
                                    </span>
                                </div>
                                <div class="resume-tailor-cost-hint" x-show="coverEstimate !== null && coverEstimate > coverBalance">
                                    <i class="fas fa-triangle-exclamation"></i> Not enough credits. Top up from <em>Credits</em> first.
                                </div>
                            </div>

                            <template x-if="coverError">
                                <div class="resume-import-error" x-text="coverError"></div>
                            </template>

                            <div class="resume-import-actions">
                                <button type="button" class="resume-add-btn"
                                        :disabled="coverBusy || coverJd.trim().length < 30
                                                    || (coverEstimate !== null && coverEstimate > coverBalance)"
                                        @click="runCoverLetter()">
                                    <i class="fas" :class="coverBusy ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                                    <span x-text="coverBusy ? 'Writing…' : 'Generate cover letter'"></span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Step 2: edit / regenerate / export ────────────── --}}
                    <template x-if="coverStep === 'edit' && coverLetter">
                        <div>
                            <div class="resume-tailor-summary-bar">
                                <span><i class="fas fa-coins"></i>
                                    Spent <strong x-text="coverLetter.credits_spent"></strong>
                                    <span x-text="coverLetter.credits_spent === 1 ? 'credit' : 'credits'"></span>
                                    · Balance: <strong x-text="coverBalance"></strong>
                                </span>
                                <span class="resume-tailor-summary-bar-bullet">
                                    <i class="fas fa-feather"></i>
                                    <span x-text="'Tone: ' + (coverLetter.tone || 'professional')"></span>
                                </span>
                                <span class="resume-tailor-summary-bar-bullet">
                                    <i class="fas fa-user-pen"></i>
                                    <span x-text="'Voice: ' + (coverLetter.ai_persona_name || 'None')"></span>
                                </span>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <input type="text" class="resume-textarea"
                                       style="font-weight:600; font-size: 13px; padding: 8px 10px;"
                                       maxlength="200"
                                       placeholder="Letter title"
                                       x-model="coverLetter.title"
                                       @input.debounce.700ms="saveCoverEdits()">
                            </div>

                            {{-- Greeting --}}
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4><i class="fas fa-handshake head-icon"></i> Greeting</h4>
                                    <button type="button" class="resume-import-mini"
                                            :disabled="coverSectionBusy === 'greeting'"
                                            @click="regenCoverSection('greeting')">
                                        <i class="fas" :class="coverSectionBusy === 'greeting' ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                        Rewrite
                                    </button>
                                </div>
                                <input type="text" class="resume-textarea"
                                       maxlength="300"
                                       x-model="coverLetter.content.greeting"
                                       @input.debounce.700ms="saveCoverEdits()">
                            </div>

                            {{-- Body paragraphs --}}
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4>
                                        <i class="fas fa-align-left head-icon"></i> Body
                                        <span class="resume-import-count"
                                              x-text="'(' + (coverLetter.content.body || []).length + ')'"></span>
                                    </h4>
                                    <button type="button" class="resume-import-mini"
                                            :disabled="coverSectionBusy === 'body'"
                                            @click="regenCoverSection('body')">
                                        <i class="fas" :class="coverSectionBusy === 'body' ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                        Rewrite
                                    </button>
                                </div>
                                <template x-for="(p, idx) in coverLetter.content.body" :key="idx">
                                    <div style="display:flex; gap:8px; align-items:flex-start; margin-bottom: 8px;">
                                        <textarea class="resume-textarea" rows="4" maxlength="2000"
                                                  :value="p"
                                                  @input="coverLetter.content.body[idx] = $event.target.value"
                                                  @input.debounce.700ms="saveCoverEdits()"
                                                  style="flex:1;"></textarea>
                                        <button type="button" class="resume-icon-btn"
                                                style="width:32px;"
                                                title="Remove paragraph"
                                                @click="removeCoverParagraph(idx)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </template>
                                <button type="button" class="resume-import-mini"
                                        :disabled="(coverLetter.content.body || []).length >= 5"
                                        @click="addCoverParagraph()">
                                    <i class="fas fa-plus"></i> Add paragraph
                                </button>
                            </div>

                            {{-- Sign-off --}}
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4><i class="fas fa-signature head-icon"></i> Sign-off</h4>
                                    <button type="button" class="resume-import-mini"
                                            :disabled="coverSectionBusy === 'sign_off'"
                                            @click="regenCoverSection('sign_off')">
                                        <i class="fas" :class="coverSectionBusy === 'sign_off' ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                        Rewrite
                                    </button>
                                </div>
                                <textarea class="resume-textarea" rows="3" maxlength="400"
                                          x-model="coverLetter.content.sign_off"
                                          @input.debounce.700ms="saveCoverEdits()"></textarea>
                            </div>

                            <template x-if="coverError">
                                <div class="resume-import-error" x-text="coverError"></div>
                            </template>

                            <div class="resume-import-actions justify-between" style="margin-top: 14px;">
                                <button type="button" class="resume-add-btn"
                                        @click="coverStep='pick'; coverError=''">
                                    <i class="fas fa-plus"></i> New letter
                                </button>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="button" class="resume-add-btn" @click="copyCoverLetter()">
                                        <i class="fas" :class="coverCopied ? 'fa-check' : 'fa-copy'"></i>
                                        <span x-text="coverCopied ? 'Copied!' : 'Copy text'"></span>
                                    </button>
                                    <a class="resume-add-btn"
                                       :href="coverLetter ? ('{{ url('/user/resume/cover-letters') }}/' + coverLetter.id + '/download') : '#'"
                                       target="_blank" rel="noopener">
                                        <i class="fas fa-file-arrow-down"></i> Download PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- History rail ──────────────────────────────────────── --}}
                <aside style="border-left: 1px solid var(--border-glass,#2a2a32); padding-left: 14px; min-width:0;">
                    <h4 style="display:flex; align-items:center; gap:8px; margin: 0 0 10px; font-size: 11px; font-weight:700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted,#9ca3af);">
                        <i class="fas fa-clock-rotate-left"></i> Saved letters
                    </h4>
                    <template x-if="!coverHistory.length">
                        <div class="resume-import-note" style="font-size: 11px;">
                            Saved letters appear here once you generate one.
                        </div>
                    </template>
                    <template x-for="h in coverHistory" :key="h.id">
                        <div class="resume-tailor-history-row" style="cursor:pointer; position:relative;"
                             :style="coverLetter && coverLetter.id === h.id ? 'background: rgba(61,107,255,0.12); border:1px solid rgba(61,107,255,0.3);' : ''"
                             @click="loadCoverLetter(h.id)">
                            <div class="resume-tailor-history-jd" x-text="h.title || h.jd_excerpt || '(untitled)'"></div>
                            <div class="resume-tailor-history-meta">
                                <span x-text="formatTailorWhen(h.created_at)"></span>
                                <span>·</span>
                                <span x-text="(h.credits_spent || 0) + ' cr'"></span>
                                <span>·</span>
                                <span x-text="h.tone || 'professional'"></span>
                                <span>·</span>
                                <span title="Voice used to generate this letter"
                                      x-text="'Voice: ' + (h.ai_persona_name || 'None')"></span>
                            </div>
                            <button type="button" class="resume-icon-btn"
                                    style="position:absolute; top:6px; right:6px; width:24px; height:24px;"
                                    title="Delete letter"
                                    @click.stop="deleteCoverLetter(h.id)">
                                <i class="fas fa-trash" style="font-size: 9px;"></i>
                            </button>
                        </div>
                    </template>
                </aside>
            </div>
        </div>
    </div>
</template>
