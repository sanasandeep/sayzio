{{-- Tailor-to-job modal: paste a JD, run AI, review per-section diff,
     accept the picks you want, apply them. All wiring lives on the
     parent resumeEditor() Alpine component (openTailor, runTailor,
     applyTailor, …). Recent runs are surfaced from the AI credit
     ledger via /resume/tailor/history. --}}
<template x-if="tailorOpen">
    <div class="resume-import-overlay" @click.self="closeTailor()">
        <div class="resume-import-modal" :class="{ 'resume-import-modal-wide': tailorStep === 'review' }">
            <div class="resume-import-head">
                <h3>
                    <i class="fas fa-wand-magic-sparkles"></i>
                    <span x-text="tailorStep === 'review' ? 'Review tailored changes' : 'Tailor to a job'"></span>
                </h3>
                <button type="button" class="resume-import-close" @click="closeTailor()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Step 1: paste JD --------------------------------------------- --}}
            <template x-if="tailorStep === 'pick'">
                <div class="resume-import-body">
                    <p class="resume-import-help">
                        Paste the job description and we'll rewrite your summary, experience bullets and suggest
                        keyword-matched skills. You'll review every change before anything is saved.
                    </p>
                    <textarea class="resume-textarea" rows="10" maxlength="20000"
                              placeholder="Paste the full job description here…"
                              x-model="tailorJd"
                              @input.debounce.500ms="refreshTailorEstimate()"></textarea>

                    <div class="resume-tailor-cost">
                        <div>
                            <i class="fas fa-coins"></i>
                            <span x-show="tailorEstimate === null && tailorJd.trim().length >= 30">Estimating…</span>
                            <span x-show="tailorEstimate === null && tailorJd.trim().length < 30">
                                Paste at least 30 characters to see the cost.
                            </span>
                            <span x-show="tailorEstimate !== null">
                                Up to
                                <strong x-text="tailorEstimate"></strong>
                                <span x-text="tailorEstimate === 1 ? 'credit' : 'credits'"></span>
                                · Balance:
                                <strong x-text="tailorBalance"></strong>
                            </span>
                        </div>
                        <div class="resume-tailor-cost-hint" x-show="tailorEstimate !== null && tailorEstimate > tailorBalance">
                            <i class="fas fa-triangle-exclamation"></i> Not enough credits. Top up from <em>Credits</em> first.
                        </div>
                    </div>

                    <template x-if="tailorError">
                        <div class="resume-import-error" x-text="tailorError"></div>
                    </template>

                    <div class="resume-import-actions">
                        <button type="button" class="resume-add-btn"
                                :disabled="tailorBusy || tailorJd.trim().length < 30
                                            || (tailorEstimate !== null && tailorEstimate > tailorBalance)"
                                @click="runTailor()">
                            <i class="fas" :class="tailorBusy ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                            <span x-text="tailorBusy ? 'Tailoring…' : 'Tailor my resume'"></span>
                        </button>
                    </div>

                    <template x-if="tailorHistory.length">
                        <div class="resume-tailor-history">
                            <h4><i class="fas fa-clock-rotate-left"></i> Recent runs</h4>
                            <template x-for="h in tailorHistory" :key="h.id">
                                <div class="resume-tailor-history-row">
                                    <div class="resume-tailor-history-jd" x-text="h.jd_excerpt || '(no excerpt)'"></div>
                                    <div class="resume-tailor-history-meta">
                                        <span x-text="formatTailorWhen(h.when)"></span>
                                        <span>·</span>
                                        <span x-text="h.credits + ' credit' + (h.credits === 1 ? '' : 's')"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Step 2: review & accept/reject ------------------------------- --}}
            <template x-if="tailorStep === 'review'">
                <div class="resume-import-body resume-import-review">
                    <div class="resume-import-picks">
                        <div class="resume-tailor-summary-bar">
                            <span><i class="fas fa-coins"></i>
                                Spent <strong x-text="tailorLastSpent"></strong>
                                <span x-text="tailorLastSpent === 1 ? 'credit' : 'credits'"></span>
                                · Balance: <strong x-text="tailorBalance"></strong>
                            </span>
                            <span class="resume-tailor-summary-bar-bullet">
                                <i class="fas fa-check-double"></i>
                                <span x-text="tailorAcceptCount() + ' accepted'"></span>
                            </span>
                        </div>

                        {{-- Keywords detected from the JD --}}
                        <template x-if="(tailorSuggestions.keywords || []).length">
                            <div class="resume-tailor-keywords">
                                <h4><i class="fas fa-tag head-icon"></i> JD keywords highlighted</h4>
                                <div class="resume-tailor-keyword-row">
                                    <template x-for="kw in tailorSuggestions.keywords" :key="kw">
                                        <span class="resume-tailor-keyword-chip" x-text="kw"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Summary diff --}}
                        <template x-if="tailorSuggestions.summary && tailorSuggestions.summary.changed">
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4><i class="fas fa-align-left head-icon"></i> Summary</h4>
                                    <label class="resume-tailor-toggle">
                                        <input type="checkbox" x-model="tailorPicks.summary">
                                        <span x-text="tailorPicks.summary ? 'Accepted' : 'Rejected'"></span>
                                    </label>
                                </div>
                                <div class="resume-tailor-diff">
                                    <div class="resume-tailor-diff-col">
                                        <div class="resume-tailor-diff-label">Current</div>
                                        <div class="resume-tailor-diff-text resume-tailor-diff-old"
                                             x-html="renderTailorDiff(tailorSuggestions.summary.current, tailorSuggestions.summary.suggested, 'old')"></div>
                                    </div>
                                    <div class="resume-tailor-diff-col">
                                        <div class="resume-tailor-diff-label">Suggested</div>
                                        <div class="resume-tailor-diff-text resume-tailor-diff-new"
                                             x-html="renderTailorDiff(tailorSuggestions.summary.current, tailorSuggestions.summary.suggested, 'new')"></div>
                                    </div>
                                </div>
                                <template x-if="tailorSuggestions.summary.rationale">
                                    <div class="resume-tailor-rationale">
                                        <i class="fas fa-lightbulb"></i>
                                        <span x-text="tailorSuggestions.summary.rationale"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Experience diffs --}}
                        <template x-if="(tailorSuggestions.experience || []).length">
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4>
                                        <i class="fas fa-briefcase head-icon"></i> Experience
                                        <span class="resume-import-count"
                                              x-text="'(' + tailorSuggestions.experience.length + ')'"></span>
                                    </h4>
                                    <div class="flex gap-2">
                                        <button type="button" class="resume-import-mini" @click="acceptAllExp(true)">All</button>
                                        <button type="button" class="resume-import-mini" @click="acceptAllExp(false)">None</button>
                                    </div>
                                </div>
                                <template x-for="row in tailorSuggestions.experience" :key="row.item_id">
                                    <div class="resume-tailor-exp">
                                        <div class="resume-tailor-exp-head">
                                            <div>
                                                <div class="resume-tailor-exp-role" x-text="row.role || '(role)'"></div>
                                                <div class="resume-tailor-exp-company" x-text="row.company"></div>
                                            </div>
                                            <label class="resume-tailor-toggle">
                                                <input type="checkbox" :value="row.item_id" x-model="tailorPicks.experience">
                                                <span x-text="tailorPicks.experience.includes(row.item_id) ? 'Accepted' : 'Rejected'"></span>
                                            </label>
                                        </div>
                                        <div class="resume-tailor-diff">
                                            <div class="resume-tailor-diff-col">
                                                <div class="resume-tailor-diff-label">Current</div>
                                                <div class="resume-tailor-diff-text resume-tailor-diff-old"
                                                     x-html="renderTailorDiff(row.current, row.suggested, 'old')"></div>
                                            </div>
                                            <div class="resume-tailor-diff-col">
                                                <div class="resume-tailor-diff-label">Suggested</div>
                                                <div class="resume-tailor-diff-text resume-tailor-diff-new"
                                                     x-html="renderTailorDiff(row.current, row.suggested, 'new')"></div>
                                            </div>
                                        </div>
                                        <template x-if="row.rationale">
                                            <div class="resume-tailor-rationale">
                                                <i class="fas fa-lightbulb"></i>
                                                <span x-text="row.rationale"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Skill additions --}}
                        <template x-if="(tailorSuggestions.skills && tailorSuggestions.skills.additions || []).length">
                            <div class="resume-import-group">
                                <div class="resume-import-group-head">
                                    <h4>
                                        <i class="fas fa-bolt head-icon"></i> Suggested skills
                                        <span class="resume-import-count"
                                              x-text="'(' + tailorSuggestions.skills.additions.length + ')'"></span>
                                    </h4>
                                    <div class="flex gap-2">
                                        <button type="button" class="resume-import-mini" @click="acceptAllSkills(true)">All</button>
                                        <button type="button" class="resume-import-mini" @click="acceptAllSkills(false)">None</button>
                                    </div>
                                </div>
                                <template x-for="(sk, idx) in tailorSuggestions.skills.additions" :key="idx">
                                    <label class="resume-tailor-skill-row">
                                        <input type="checkbox" :value="idx" x-model="tailorPicks.skills">
                                        <div>
                                            <div class="resume-tailor-skill-name" x-text="sk.name"></div>
                                            <div class="resume-tailor-skill-rationale" x-text="sk.rationale"></div>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </template>

                        <template x-if="!tailorHasAnyChanges()">
                            <div class="resume-import-note">
                                Your resume already matches this JD well, nothing to change.
                            </div>
                        </template>

                        <template x-if="tailorError">
                            <div class="resume-import-error" x-text="tailorError"></div>
                        </template>

                        <div class="resume-import-actions justify-between">
                            <button type="button" class="resume-add-btn" @click="tailorStep='pick'; tailorError=''">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="resume-add-btn"
                                    :disabled="tailorBusy || !tailorAcceptCount()"
                                    @click="applyTailor()">
                                <i class="fas" :class="tailorBusy ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                                <span x-text="tailorBusy ? 'Saving…' : 'Apply ' + tailorAcceptCount() + ' change' + (tailorAcceptCount() === 1 ? '' : 's')"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
