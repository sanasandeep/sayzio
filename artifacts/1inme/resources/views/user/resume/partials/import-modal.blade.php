{{-- Import modal: 4 source tabs (file, LinkedIn, bio link, AI) and a
     unified Review & Merge step. All wiring lives on the parent
     resumeEditor() Alpine component (openImport, runImport*, applyMerge…). --}}
<template x-if="importOpen">
    <div class="resume-import-overlay" @click.self="closeImport()">
        <div class="resume-import-modal" :class="{ 'resume-import-modal-wide': importStep === 'review' }">
            <div class="resume-import-head">
                <h3>
                    <i class="fas fa-file-import"></i>
                    <span x-text="importStep === 'review' ? 'Review &amp; merge' : 'Import resume content'"></span>
                </h3>
                <button type="button" class="resume-import-close" @click="closeImport()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Step 1: pick a method ---------------------------------------- --}}
            <template x-if="importStep === 'pick'">
                <div class="resume-import-body">
                    <div class="resume-import-tabs">
                        <template x-for="t in importTabs" :key="t.key">
                            <button type="button"
                                :class="{ active: importTab === t.key }"
                                @click="importTab = t.key">
                                <i class="fas" :class="t.icon"></i>
                                <span x-text="t.label"></span>
                            </button>
                        </template>
                    </div>

                    {{-- File upload --}}
                    <div x-show="importTab === 'file'" class="resume-import-pane">
                        <p class="resume-import-help">Upload a PDF or DOCX. We'll extract sections and let you choose what to keep.</p>
                        <label class="resume-import-drop">
                            <input type="file" accept=".pdf,.doc,.docx" @change="importFile = $event.target.files[0]; importError=''">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <span x-text="importFile ? importFile.name : 'Choose a PDF or DOCX (max 20 MB)'"></span>
                        </label>
                        <div class="resume-import-actions">
                            <button type="button" class="resume-add-btn" :disabled="!importFile || importBusy"
                                @click="runImportFile()">
                                <i class="fas" :class="importBusy ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                                <span x-text="importBusy ? 'Reading file…' : 'Parse file'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- LinkedIn --}}
                    <div x-show="importTab === 'linkedin'" class="resume-import-pane">
                        <p class="resume-import-help">
                            Paste your LinkedIn profile URL. For best results also upload your "Save to PDF" export
                            from LinkedIn — we can't read profiles directly.
                        </p>
                        <input type="url" class="resume-input mb-3" placeholder="https://www.linkedin.com/in/yourhandle"
                               x-model="importLinkedinUrl">
                        <label class="resume-import-drop">
                            <input type="file" accept=".pdf" @change="importFile = $event.target.files[0]; importError=''">
                            <i class="fas fa-file-pdf"></i>
                            <span x-text="importFile ? importFile.name : 'Optional: drop your LinkedIn export PDF'"></span>
                        </label>
                        <div class="resume-import-actions">
                            <button type="button" class="resume-add-btn"
                                :disabled="(!importLinkedinUrl && !importFile) || importBusy"
                                @click="runImportLinkedin()">
                                <i class="fas" :class="importBusy ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                                <span x-text="importBusy ? 'Reading…' : 'Pull from LinkedIn'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Bio link --}}
                    <div x-show="importTab === 'biolink'" class="resume-import-pane">
                        <p class="resume-import-help">
                            Pull your name, social links, posts (as portfolio projects) and Link in Bio blocks from your
                            existing 1INME profile.
                        </p>
                        <div class="resume-import-actions">
                            <button type="button" class="resume-add-btn" :disabled="importBusy" @click="runImportBiolink()">
                                <i class="fas" :class="importBusy ? 'fa-spinner fa-spin' : 'fa-link'"></i>
                                <span x-text="importBusy ? 'Loading…' : 'Pull from my Link in Bio'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- AI assist --}}
                    <div x-show="importTab === 'ai'" class="resume-import-pane">
                        <p class="resume-import-help">
                            Describe yourself, your role and your wins. AI will draft a summary, experience bullets and
                            skills you can review before merging. Uses your AI credits.
                        </p>
                        <textarea class="resume-textarea mb-3" rows="5" maxlength="1500"
                                  placeholder="e.g. Senior product designer with 8 years in fintech. Led the redesign of…"
                                  x-model="importAiPrompt"></textarea>
                        <div class="flex flex-wrap gap-3 mb-3">
                            <template x-for="s in ['summary','experience','skills','projects']" :key="s">
                                <label class="resume-import-chip">
                                    <input type="checkbox" :value="s" x-model="importAiSections">
                                    <span x-text="s"></span>
                                </label>
                            </template>
                        </div>
                        <div class="resume-import-actions">
                            <button type="button" class="resume-add-btn"
                                :disabled="importAiPrompt.trim().length < 10 || !importAiSections.length || importBusy"
                                @click="runImportAi()">
                                <i class="fas" :class="importBusy ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                                <span x-text="importBusy ? 'Drafting…' : 'Draft with AI'"></span>
                            </button>
                        </div>
                    </div>

                    <template x-if="importError">
                        <div class="resume-import-error" x-text="importError"></div>
                    </template>
                </div>
            </template>

            {{-- Step 2: Review & Merge --------------------------------------- --}}
            <template x-if="importStep === 'review'">
                <div class="resume-import-body resume-import-review">
                    <div class="resume-import-picks">
                    <template x-if="importCandidates.notes">
                        <div class="resume-import-note" x-text="importCandidates.notes"></div>
                    </template>

                    {{-- Header fields --}}
                    <template x-if="hasHeaderCandidates()">
                        <div class="resume-import-group">
                            <div class="resume-import-group-head">
                                <h4><i class="fas fa-id-card head-icon"></i> Header</h4>
                                <select class="resume-input" x-model="importPicks.header.mode">
                                    <option value="skip">Skip</option>
                                    <option value="replace">Replace existing</option>
                                    <option value="append">Append (combine)</option>
                                </select>
                            </div>
                            <template x-for="(v,k) in importCandidates.header" :key="k">
                                <label class="resume-import-row" :class="{ disabled: importPicks.header.mode === 'skip' }">
                                    <input type="checkbox" :value="k" x-model="importPicks.header.fields"
                                           :disabled="importPicks.header.mode === 'skip'">
                                    <div>
                                        <div class="resume-import-row-key" x-text="k"></div>
                                        <div class="resume-import-row-val" x-text="v"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </template>

                    {{-- Summary --}}
                    <template x-if="importCandidates.summary">
                        <div class="resume-import-group">
                            <div class="resume-import-group-head">
                                <h4><i class="fas fa-align-left head-icon"></i> Summary</h4>
                                <select class="resume-input" x-model="importPicks.summary.mode">
                                    <option value="skip">Skip</option>
                                    <option value="replace">Replace existing</option>
                                    <option value="append">Append (combine)</option>
                                </select>
                            </div>
                            <div class="resume-import-summary" x-text="importCandidates.summary"></div>
                        </div>
                    </template>

                    {{-- Items grouped by section type --}}
                    <template x-for="grp in groupedCandidateItems()" :key="grp.type">
                        <div class="resume-import-group">
                            <div class="resume-import-group-head">
                                <h4>
                                    <i class="fas head-icon" :class="grp.icon"></i>
                                    <span x-text="grp.label"></span>
                                    <span class="resume-import-count" x-text="'(' + grp.items.length + ')'"></span>
                                </h4>
                                <div class="flex gap-2">
                                    <button type="button" class="resume-import-mini" @click="selectAllOfType(grp.type, true)">All</button>
                                    <button type="button" class="resume-import-mini" @click="selectAllOfType(grp.type, false)">None</button>
                                </div>
                            </div>
                            <template x-for="row in grp.items" :key="row.idx">
                                <label class="resume-import-row">
                                    <input type="checkbox" :value="row.idx" x-model="importPicks.items">
                                    <div>
                                        <div class="resume-import-row-key" x-text="describeCandidate(row.cand)"></div>
                                        <div class="resume-import-row-val" x-text="describeCandidateSub(row.cand)"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </template>

                    <template x-if="!hasAnyCandidates()">
                        <div class="resume-import-note">Nothing was extracted that we can merge.</div>
                    </template>

                    <template x-if="importError">
                        <div class="resume-import-error" x-text="importError"></div>
                    </template>

                    <div class="resume-import-actions justify-between">
                        <button type="button" class="resume-add-btn" @click="importStep='pick'; importError=''">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="resume-add-btn" :disabled="importBusy || !pickCount()"
                            @click="applyMerge()">
                            <i class="fas" :class="importBusy ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                            <span x-text="importBusy ? 'Merging…' : 'Add ' + pickCount() + ' selected'"></span>
                        </button>
                    </div>
                    </div>{{-- /.resume-import-picks --}}

                    {{-- Live preview of the merged resume. Updates whenever
                         picks change via $watch('importPicks', …, deep:true)
                         in resumeEditor(). --}}
                    <aside class="resume-import-preview-pane" aria-label="Resume preview">
                        <div class="resume-import-preview-head">
                            <i class="fas fa-eye"></i>
                            <span>Live preview</span>
                            <span class="resume-import-preview-hint" x-show="pickCount() > 0"
                                  x-text="'+' + pickCount() + ' change' + (pickCount()===1?'':'s')"></span>
                        </div>
                        <div class="resume-import-preview-frame">
                            <div class="preview-page" x-html="importPreviewHtml"></div>
                        </div>
                    </aside>
                </div>
            </template>
        </div>
    </div>
</template>
