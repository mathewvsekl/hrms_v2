# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests\dashboard.spec.js >> Functional Flow: Dashboard
- Location: tests\dashboard.spec.js:31:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: page.goto: Test timeout of 30000ms exceeded.
Call log:
  - navigating to "https://emm.anedins.com/dashboard", waiting until "networkidle"

```

# Page snapshot

```yaml
- generic [ref=e3]:
  - complementary [ref=e4]:
    - generic [ref=e5]:
      - img "Avantgarde HRMS Logo" [ref=e6]
      - generic [ref=e7]: AVANTGARDE HRMS
    - navigation [ref=e8]:
      - link "DASHBOARD" [ref=e9] [cursor=pointer]:
        - /url: /dashboard
        - img [ref=e10]
        - generic [ref=e15]: DASHBOARD
      - generic [ref=e17]:
        - button "Operations" [ref=e18] [cursor=pointer]:
          - generic [ref=e19]: Operations
          - img [ref=e20]
        - generic [ref=e22]:
          - link "Attendance" [ref=e23] [cursor=pointer]:
            - /url: /attendance
            - img [ref=e24]
            - generic [ref=e27]: Attendance
          - link "Leave" [ref=e28] [cursor=pointer]:
            - /url: /leave
            - img [ref=e29]
            - generic [ref=e31]: Leave
          - link "Appraisals" [ref=e32] [cursor=pointer]:
            - /url: /appraisals
            - img [ref=e33]
            - generic [ref=e34]: Appraisals
      - generic [ref=e35]:
        - button "Workforce" [ref=e36] [cursor=pointer]:
          - generic [ref=e37]: Workforce
          - img [ref=e38]
        - generic [ref=e40]:
          - link "Employees" [ref=e41] [cursor=pointer]:
            - /url: /employees
            - img [ref=e42]
            - generic [ref=e47]: Employees
          - link "Onboarding" [ref=e48] [cursor=pointer]:
            - /url: /onboarding
            - img [ref=e49]
            - generic [ref=e52]: Onboarding
          - link "Offboarding" [ref=e53] [cursor=pointer]:
            - /url: /offboarding
            - img [ref=e54]
            - generic [ref=e57]: Offboarding
      - generic [ref=e58]:
        - button "Finance" [ref=e59] [cursor=pointer]:
          - generic [ref=e60]: Finance
          - img [ref=e61]
        - generic [ref=e63]:
          - link "Payroll" [ref=e64] [cursor=pointer]:
            - /url: /payroll
            - img [ref=e65]
            - generic [ref=e68]: Payroll
          - link "Salary Advances" [ref=e69] [cursor=pointer]:
            - /url: /salary-advances
            - img [ref=e70]
            - generic [ref=e73]: Salary Advances
          - link "PaySlip" [ref=e74] [cursor=pointer]:
            - /url: /payslips
            - img [ref=e75]
            - generic [ref=e78]: PaySlip
      - generic [ref=e79]:
        - button "System" [ref=e80] [cursor=pointer]:
          - generic [ref=e81]: System
          - img [ref=e82]
        - generic [ref=e84]:
          - link "Assets" [ref=e85] [cursor=pointer]:
            - /url: /assets
            - img [ref=e86]
            - generic [ref=e90]: Assets
          - link "Reports" [ref=e91] [cursor=pointer]:
            - /url: /reports
            - img [ref=e92]
            - generic [ref=e95]: Reports
          - link "Configurations" [ref=e96] [cursor=pointer]:
            - /url: /admin
            - img [ref=e97]
            - generic [ref=e100]: Configurations
    - generic [ref=e101]:
      - link "Employee Profile" [ref=e102] [cursor=pointer]:
        - /url: /employee-profile
        - img [ref=e103]
        - generic [ref=e106]: Employee Profile
      - generic [ref=e107]:
        - generic [ref=e108]: © 2026 Avantgarde HRMS SYSTEM
        - generic [ref=e109]: Version v3.0.26-emm-build2
  - generic [ref=e110]:
    - banner [ref=e111]:
      - generic [ref=e113]:
        - heading "HR Administration Centre" [level=1] [ref=e115]
        - paragraph [ref=e116]: AVANTGARDE HRMS Operational Overview
      - generic [ref=e117]:
        - generic [ref=e118] [cursor=pointer]:
          - img [ref=e120]
          - generic [ref=e125]: SuperAdmin
        - button "Notifications" [ref=e128] [cursor=pointer]:
          - img [ref=e129]
          - generic [ref=e132]: 9+
        - button "Sign Out" [ref=e134] [cursor=pointer]:
          - img [ref=e135]
          - generic [ref=e138]: Sign Out
    - main [ref=e139]:
      - generic [ref=e140]:
        - button "Refresh Analytics" [ref=e142] [cursor=pointer]:
          - img [ref=e143]
          - generic [ref=e148]: Refresh Analytics
        - generic [ref=e149]:
          - generic [ref=e150]:
            - generic [ref=e152]:
              - generic [ref=e153]: Total Headcount
              - img [ref=e155]
            - generic [ref=e160]: "22"
            - generic [ref=e161]:
              - generic [ref=e162]: 21 ACTIVE
              - generic [ref=e163]: 1 PENDING
          - generic [ref=e164]:
            - generic [ref=e166]:
              - generic [ref=e167]: Operational Availability
              - img [ref=e169]
            - generic [ref=e171]: 43%
            - generic [ref=e173]: 9/21 Personnel Active
          - generic [ref=e174]:
            - generic [ref=e176]:
              - generic [ref=e177]: Payroll Compliance
              - img [ref=e179]
            - generic [ref=e182]: 100%
            - generic [ref=e184]: Validation Pending
          - generic [ref=e185]:
            - generic [ref=e187]:
              - generic [ref=e188]: Performance Insights
              - img [ref=e190]
            - generic [ref=e193]: "0"
            - generic [ref=e195]: ACTIVE AUDITS
        - generic [ref=e196]:
          - generic [ref=e197]:
            - generic [ref=e198]:
              - generic [ref=e199]:
                - img [ref=e200]
                - text: Regional Distribution
              - generic [ref=e203] [cursor=pointer]:
                - text: Detailed Analysis
                - img [ref=e204]
            - generic [ref=e206]:
              - generic [ref=e207]:
                - generic [ref=e208]:
                  - img "ug" [ref=e210]
                  - generic [ref=e211] [cursor=pointer]: Uganda
                - generic [ref=e212]:
                  - generic [ref=e213]: 90%
                  - generic [ref=e214]:
                    - 'generic "present: 8" [ref=e215]'
                    - 'generic "work_from_home: 1" [ref=e216]'
                    - 'generic "PL: 1" [ref=e217]'
                  - generic [ref=e218]: 9/10 PRESENT
              - generic [ref=e219]:
                - generic [ref=e220]:
                  - img "ae" [ref=e222]
                  - generic [ref=e223] [cursor=pointer]: United Arab Emirates
                - generic [ref=e224]:
                  - generic [ref=e225]: 0%
                  - generic [ref=e228]: 0/7 PRESENT
              - generic [ref=e229]:
                - generic [ref=e230]:
                  - img "in" [ref=e232]
                  - generic [ref=e233] [cursor=pointer]: India
                - generic [ref=e234]:
                  - generic [ref=e235]: 0%
                  - generic [ref=e238]: 0/3 PRESENT
              - generic [ref=e239]:
                - generic [ref=e240]:
                  - img "ke" [ref=e242]
                  - generic [ref=e243] [cursor=pointer]: Kenya
                - generic [ref=e244]:
                  - generic [ref=e245]: 0%
                  - generic [ref=e248]: 0/1 PRESENT
              - generic [ref=e249]:
                - generic [ref=e250]:
                  - generic [ref=e251]:
                    - img "tz"
                  - generic [ref=e252] [cursor=pointer]: Tanzania
                - generic [ref=e253]:
                  - generic [ref=e254]: 0%
                  - generic [ref=e257]: 0/1 PRESENT
          - generic [ref=e258]:
            - generic [ref=e260]:
              - img [ref=e261]
              - text: Milestones & Anniversaries
            - generic [ref=e267]: CELEBRATIONS • NEXT 7 DAYS
            - generic [ref=e269]: No upcoming milestones.
        - generic [ref=e270]:
          - generic [ref=e271]:
            - generic [ref=e272]:
              - generic [ref=e273]:
                - img [ref=e274]
                - text: Action Required
              - button "View All Pending" [ref=e277] [cursor=pointer]:
                - text: View All Pending
                - img [ref=e278]
            - generic [ref=e280]:
              - generic [ref=e281] [cursor=pointer]:
                - img [ref=e283]
                - generic [ref=e285]:
                  - generic [ref=e286]:
                    - generic [ref=e287]: Draft Leave Request
                    - generic [ref=e288]: 11/06/2026 - 11/06/2026
                  - generic [ref=e289]: Moses Ikwara (Annual Leave)
                - img [ref=e290]
              - generic [ref=e292] [cursor=pointer]:
                - img [ref=e294]
                - generic [ref=e296]:
                  - generic [ref=e297]:
                    - generic [ref=e298]: Draft Leave Request
                    - generic [ref=e299]: 08/06/2026 - 08/06/2026
                  - generic [ref=e300]: Solomon Kiwunda (Annual Leave)
                - img [ref=e301]
              - generic [ref=e303] [cursor=pointer]:
                - img [ref=e305]
                - generic [ref=e307]:
                  - generic [ref=e308]:
                    - generic [ref=e309]: Draft Leave Request
                    - generic [ref=e310]: 12/02/2026 - 13/05/2026
                  - generic [ref=e311]: Angella Nyinimuntu (Maternity Leave)
                - img [ref=e312]
              - generic [ref=e314] [cursor=pointer]:
                - img [ref=e316]
                - generic [ref=e318]:
                  - generic [ref=e319]:
                    - generic [ref=e320]: Draft Leave Request
                    - generic [ref=e321]: 10/02/2026 - 11/02/2026
                  - generic [ref=e322]: Angella Nyinimuntu (Sick Leave)
                - img [ref=e323]
              - generic [ref=e325] [cursor=pointer]: + 19 more line items below
          - generic [ref=e326]:
            - generic [ref=e328]:
              - img [ref=e329]
              - text: Recruitment Pipeline
            - generic [ref=e332]:
              - generic [ref=e333] [cursor=pointer]:
                - generic [ref=e334]: TALENT ACQUISITION
                - generic [ref=e335]:
                  - generic [ref=e336]: "1"
                  - img [ref=e337]
                - generic [ref=e340]: PERSONNEL IN ONBOARDING
              - generic [ref=e341] [cursor=pointer]:
                - generic [ref=e342]: TALENT RETENTION RISK
                - generic [ref=e343]:
                  - generic [ref=e344]: "0"
                  - img [ref=e345]
                - generic [ref=e348]: PERSONNEL IN SEPARATION
```

# Test source

```ts
  1  | const { test, expect } = require('@playwright/test');
  2  | 
  3  | test.use({
  4  |   storageState: async ({ browser }, use) => {
  5  |     await use({
  6  |       cookies: [],
  7  |       origins: [
  8  |         {
  9  |           origin: 'https://emm.anedins.com',
  10 |           localStorage: [
  11 |             {
  12 |               name: 'hrms_auth_token',
  13 |               value: '485a656aaebb7a1494dfb34ee7ee8188236da86e1f60be5a5812266e02bb412c'
  14 |             },
  15 |             {
  16 |               name: 'hrms_user',
  17 |               value: JSON.stringify({
  18 |                 email: 'mathew.vsekl@gmail.com',
  19 |                 user_id: 1,
  20 |                 role: 'SuperAdmin',
  21 |                 username: 'mathew.vsekl@gmail.com'
  22 |               })
  23 |             }
  24 |           ]
  25 |         }
  26 |       ]
  27 |     });
  28 |   }
  29 | });
  30 | 
  31 | test('Functional Flow: Dashboard', async ({ page }) => {
  32 |   test.setTimeout(30000);
  33 | 
  34 |   console.log('1. Navigating to Dashboard...');
> 35 |   await page.goto('https://emm.anedins.com/dashboard', { waitUntil: 'networkidle' });
     |              ^ Error: page.goto: Test timeout of 30000ms exceeded.
  36 | 
  37 |   console.log('2. Verifying Dashboard loads...');
  38 |   // Wait for some common dashboard elements to be visible
  39 |   await page.waitForSelector('text=Total Employees', { timeout: 15000 }).catch(() => console.log('Total Employees text not found'));
  40 |   
  41 |   // Verify user greeting or quick actions
  42 |   const quickActions = page.locator('text=Quick Actions');
  43 |   if (await quickActions.isVisible()) {
  44 |     console.log('Quick Actions section found.');
  45 |   }
  46 | 
  47 |   // Click on a Quick Action or tab if present (e.g., HR, Admin, Personal)
  48 |   const hrTab = page.locator('button:has-text("HR Overview")').first();
  49 |   if (await hrTab.isVisible()) {
  50 |     await hrTab.click();
  51 |     console.log('Clicked HR Overview tab.');
  52 |   }
  53 | 
  54 |   console.log('✅ Dashboard test completed successfully!');
  55 | });
  56 | 
```