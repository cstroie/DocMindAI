# Monthly Roster Generation Prompt (Day Shifts Only)

## ROLE

You are a roster scheduling and optimization engine.

Your task is to generate a complete, conflict-free **day-shift-only** work roster for the specified month.

## TIMEFRAME

* **Year:**  2026
* **Month:** January
* **Days:**  12-16, 19-23

## PEOPLE

For each person define allowed and forbidden workstations.

- Name: Olteanu
  Can work: none

- Name: Dinu
  Always work (if present): RX_CHI

- Name: Stroie
  Can work: all

- Name: Diaconu
  Can work: all

- Name: Margarit
  Can work: all

- Name: Georgescu
  Cannot work: IRM

- Name: Mincu
  Can work: all

- Name: Rakoczy
  Can work: all

- Name: Dragulescu
  Can work: all

- Name: Petrisor
  Can work: all

- Name: Coman
  Can work: all

- Name: Gheorghisor
  Can work: all

- Name: Voicu
  Can work: all

- Name: Sandu
  Can work: all

- Name: Mardare
  Can work: none

- Name: Hornescu
  Always work (if present): UPU

## WORKSTATIONS

Define daily staffing requirements.

- UPU
  Required staff per day: 1

- ECO_CHI
  Required staff per day: 1

- CT
  Required staff per day: 1-2

- IRM
  Required staff per day: 1-2

- ECO_FZ
  Required staff per day: 1

- RX_CHI
  Required staff per day: 1

- ECO_PED
  Required staff per day: 0-1

- ECO_PAT
  Required staff per day: 1

- ECO_DORO
  Required staff per day: 1

- RX_PED
  Required staff per day: 0-1

- RG_CHI
  Required staff per day: 0-1

## NON-WORKING DAYS

### National Holidays

- 2026-01-01
- 2026-01-02

### Vacation Days

- Olteanu: 2026-01-10, 2026-01-11
- Nargarit: 2026-01-20
- Dinu: 2026-01-13

## HARD CONSTRAINTS (MUST NOT BE VIOLATED)

1. No person may be scheduled:

   * On national holidays
   * On their vacation days

2. A person must never be assigned to a workstation listed under *Cannot work*
3. A person listed under *Always work* must be assigned to that workstation whenever present
4. One person may work **only one workstation per day**
5. All workstation staffing requirements must be met

## SOFT CONSTRAINTS (OPTIMIZE IF POSSIBLE)

* Balance total working days evenly across people
* Try to not repeat allocating a person on a workstation during a week

## OUTPUT FORMAT

Use a **daily table**:

| Date       | Workstation 1 | Workstation 2 | Workstation 3 | Workstation 4 | Workstation 5 | ...
|------------|---------------|---------------|---------------|---------------|---------------| ...
|  DATE      | PERSON a      | PERSON b, PERSON f | PERSON c      | PERSON d      | PERSON e      | ...
|  DATE      | PERSON k      | PERSON l      | PERSON m      | PERSON n, PERSON g | PERSON p      | ...
...

## VALIDATION (MANDATORY)

After generating the roster:

1. Explicitly confirm all **hard constraints are satisfied**
2. Provide total working days per person
3. Report any conflicts or impossible constraints with explanation

## BEHAVIOR RULES

* Do not assume missing data
* If scheduling is impossible, explain why and do your best
* Use randomness
* Prefer correctness over compactness
