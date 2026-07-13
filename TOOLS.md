# Tool Quality Review — Removal Candidates

**Date:** 2026-07-13
**Scope:** Editorial review of all 33 tool prompts under `tools/<cat>/<id>.json`.
**Status:** Recommendation only — no tools removed. For later review/decision.

## Method & caveat

Each tool's prompt was read and assessed on: clarity and specificity of the
prompt, safety-awareness (for clinical tools), differentiation from other
tools, and fit with the app's stated **medical / clinical** mission.

⚠️ **There is no usage analytics.** "Low use" below is a proxy for *low fit
with the clinical mission, low differentiation, or redundancy with a stronger
tool* — not measured traffic. Confirm against real usage before deleting
anything users may rely on.

Removing a tool = delete its `tools/<cat>/<id>.json` file **and** its entry in
the `"tools"` map in `config.json`. No code changes are needed (tools are
auto-discovered). Grep the codebase for the tool id first to be safe.

---

## 🔴 Recommended for removal (6)

Ranked most- to least-clear-cut.

| Tool | Category | Reason |
|---|---|---|
| `inv` — Inversion Analyzer | cpr | **Off-mission.** Charlie Munger "inversion" productivity exercise; no clinical/document purpose. Well-written but pure mission drift. |
| `cta` — Critical Thinking Analyzer | cpr | **Off-mission.** Generic "surface hidden questions / black-swan scenarios" tool. Not medical. |
| `rdg` — Reader's Digest | cpr | **Off-mission + overlap.** Generic "human-interest" summarizer, explicitly non-clinical in tone; overlaps `wps` and `apa`. |
| `etp` — Education Programme Analyzer | res | **Niche / off-remit.** Education-policy evaluation (UNESCO / UN SDG-4). Outside the medical scope. |
| `stp` — Summarize This Paper | res | **Redundant.** `apa` does paper analysis far more thoroughly and `sml` covers literature; `stp` is two thin 3–4 field summaries. |
| `sde` — Structured Data Extractor | cpr | **Weakest prompt in the set** (4 lines, no rules/guidance). Either upgrade it or drop it in favour of `ade`, a proper structured extractor. Not off-mission — the only one of the six that's a quality rather than a fit problem. |

**Clearest cuts:** `inv`, `cta`, `rdg`, `etp` (off-mission).
**Close behind:** `stp` (redundant), `sde` (low quality — or upgrade instead).

Removing all six: **33 → 27 tools**, tightening the app around its
clinical / radiology / research core.

---

## 🟡 Lower value / some overlap — keep, but watch (6)

| Tool | Note |
|---|---|
| `pec` — Patient Education Content | Generic version of `rex`/`mex`; reasonable catch-all. |
| `mex` — Medication Explainer | Overlaps `pre` (far more thorough) but simpler/patient-facing. |
| `dqc` — Doctor Questions Creator | Thin; its entire output is already a sub-section inside `rex` and `dsn`. |
| `eml` — Email Generator | Non-medical but genuinely utility-shaped; fine. |
| `wpc` / `wps` — Web tools | Utility/dev; fine now that they use the lynx text dump. |
| `sta` — Survey Tool Analyzer | Niche research tool; competent prompt. |

---

## 🟢 Keep — strong, differentiated (18)

`rex`, `mrs`, `dsn`, `cpn`, `pre`, `agr`, `wir`, `crs`, `hda`, `dps`, `rrs`,
`rdd`, `soap`, `sbar`, `anm`, `apa`, `ade`, `sml`

Detailed, role-specific, and safety-aware. `mrs` (pediatric MRS) and the
radiology-admin trio (`crs` / `hda` / `dps`) are flagship-quality and unique.

`exp` (Experiment Tool) is intentionally prompt-less (user supplies the
prompt) — a dev playground, not scored here.

---

## Prompt defects fixed (2026-07-13)

Fixed independently of any removal decision:

- **`mrs`** — `task` said "generate a structured report in `{language}`", where
  `{language}` resolves to the language *code* (`en`/`ro`) → "a report in ro".
  Removed; `{language_instruction}` already handles output language.
- **`pec`** — stray trailing `"` at the end of the example output.
- **`soap`** — a loose sentence was wedged mid-structure into the `format` field;
  removed.
