# Hospital Discharge Paper Analysis

Analyze the following hospital discharge paper and extract the key medical information in a structured format. Present your findings in markdown format.

## Structure to Extract:

### 1. Patient Information
- Full name
- Age
- Gender
- Date of admission
- Date of discharge
- Hospital/Department
- Attending physician

### 2. Admission Diagnosis
- Primary diagnosis at admission
- Secondary diagnoses (if any)
- Reason for hospitalization

### 3. Final Diagnosis
- Primary diagnosis at discharge
- Secondary diagnoses (if any)
- Any changes from admission diagnosis

### 4. Subjective Information (S)
- Chief complaints reported by patient
- History of present illness (HPI)
- Patient's reported symptoms
- Duration and progression of symptoms

### 5. Objective Information (O)
- Vital signs on admission
- Physical examination findings
- Laboratory test results
- Imaging study results
- Other diagnostic findings

### 6. Medical History
- Past medical history
- Past surgical history
- Allergies
- Current medications
- Family medical history
- Social history (smoking, alcohol, etc.)

### 7. Hospital Course
- Treatment administered
- Procedures performed
- Medications given during stay
- Response to treatment
- Any complications

### 8. Investigations
- Laboratory tests performed
- Imaging studies conducted
- Specialized tests
- Pathology reports (if any)

### 9. Discharge Information
- Discharge medications
- Follow-up instructions
- Referrals to specialists
- Activity restrictions
- Dietary recommendations
- Warning signs to watch for

### 10. Summary
- Brief summary of hospitalization
- Key findings
- Treatment outcomes
- Recommendations for ongoing care

## Instructions:
1. Extract all relevant information from the discharge paper
2. Organize information under appropriate headings
3. Use bullet points for lists
4. Present numerical data clearly
5. Note any significant changes during hospitalization
6. Highlight any follow-up requirements
7. Use markdown formatting for clarity

## Output Format:
```markdown
# Hospital Discharge Summary

## Patient Information
- **Name:** [Patient Name]
- **Age:** [Age]
- **Gender:** [Gender]
- **Admission Date:** [Date]
- **Discharge Date:** [Date]
- **Hospital/Department:** [Name]
- **Attending Physician:** [Name]

## Admission Diagnosis
- **Primary:** [Diagnosis]
- **Secondary:** [Diagnosis if any]

## Final Diagnosis
- **Primary:** [Diagnosis]
- **Secondary:** [Diagnosis if any]

## Subjective (S)
- **Chief Complaints:** [List]
- **History of Present Illness:** [Description]
- **Reported Symptoms:** [List]

## Objective (O)
- **Vital Signs on Admission:** [Values]
- **Physical Examination:** [Findings]
- **Laboratory Results:** [Key findings]
- **Imaging Results:** [Key findings]

## Medical History
- **Past Medical History:** [List]
- **Past Surgical History:** [List]
- **Allergies:** [List]
- **Current Medications:** [List]

## Hospital Course
- **Treatments Administered:** [List]
- **Procedures Performed:** [List]
- **Response to Treatment:** [Description]
- **Complications:** [List if any]

## Investigations
- **Laboratory Tests:** [List]
- **Imaging Studies:** [List]
- **Specialized Tests:** [List]

## Discharge Information
- **Medications:** [List with dosages]
- **Follow-up Instructions:** [Details]
- **Referrals:** [List]
- **Activity Restrictions:** [Details]
- **Dietary Recommendations:** [Details]
- **Warning Signs:** [List]

## Summary
[Brief summary of hospitalization, key findings, treatment outcomes, and recommendations for ongoing care]
```
