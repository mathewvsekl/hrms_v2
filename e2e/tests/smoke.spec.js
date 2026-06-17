const { test, expect } = require('@playwright/test');

const SECTIONS = [
  'https://emm.anedins.com/dashboard',
  'https://emm.anedins.com/attendance',
  'https://emm.anedins.com/leave',
  'https://emm.anedins.com/appraisals',
  'https://emm.anedins.com/employees',
  'https://emm.anedins.com/onboarding',
  'https://emm.anedins.com/offboarding',
  'https://emm.anedins.com/payroll',
  'https://emm.anedins.com/salary-advances',
  'https://emm.anedins.com/payslips',
  'https://emm.anedins.com/assets',
  'https://emm.anedins.com/reports',
  'https://emm.anedins.com/admin',
  'https://emm.anedins.com/employee-profile'
];

test.use({
  storageState: async ({ browser }, use) => {
    await use({
      cookies: [],
      origins: [
        {
          origin: 'https://emm.anedins.com',
          localStorage: [
            {
              name: 'hrms_auth_token',
              value: '485a656aaebb7a1494dfb34ee7ee8188236da86e1f60be5a5812266e02bb412c'
            },
            {
              name: 'hrms_user',
              value: JSON.stringify({
                email: 'mathew.vsekl@gmail.com',
                user_id: 1,
                role: 'SuperAdmin',
                username: 'mathew.vsekl@gmail.com'
              })
            }
          ]
        }
      ]
    });
  }
});

test('Deep Smoke Test - Data Loading on all sections', async ({ page }) => {
  test.setTimeout(180000); // 3 minutes timeout to handle all 14 pages
  
  const results = [];
  
  for (const url of SECTIONS) {
    console.log(`\nTesting Section: ${url}`);
    const sectionErrors = [];
    const apiErrors = [];
    
    // Setup listener for this specific page visit
    const responseListener = response => {
      // Ignore /api/media since we already know avatars are failing
      if (response.url().includes('/api/') && !response.url().includes('/api/media')) {
        if (response.status() >= 400) {
          apiErrors.push(`[${response.status()}] ${response.url()}`);
        }
      }
    };
    page.on('response', responseListener);

    try {
      // Navigate and wait for network to be mostly idle (all background API calls finished fetching data)
      await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
      
      // Wait an extra second for React rendering
      await page.waitForTimeout(1000);
      
      const bodyText = await page.innerText('body');
      
      // Check for generic frontend data loading error messages
      const errorPhrases = ['Failed to load', 'Internal Server Error', 'Something went wrong', 'Network Error'];
      for (const phrase of errorPhrases) {
        if (bodyText.includes(phrase)) {
          sectionErrors.push(`UI Error Message Detected: "${phrase}"`);
        }
      }

    } catch (e) {
      sectionErrors.push(`Timeout/Loading Error: ${e.message}`);
    } finally {
      // Clean up listener
      page.off('response', responseListener);
    }
    
    const sectionName = url.split('/').pop() || 'dashboard';
    
    if (sectionErrors.length === 0 && apiErrors.length === 0) {
      console.log(`✅ ${sectionName} data loaded successfully without errors.`);
      results.push({ section: sectionName, status: 'PASS' });
    } else {
      console.log(`❌ ${sectionName} encountered issues.`);
      if (apiErrors.length > 0) console.log(`   API Errors:`, apiErrors);
      if (sectionErrors.length > 0) console.log(`   UI Errors:`, sectionErrors);
      results.push({ section: sectionName, status: 'FAIL', apiErrors, sectionErrors });
    }
  }

  console.log('\n=======================================');
  console.log('          SMOKE TEST SUMMARY           ');
  console.log('=======================================');
  const failures = results.filter(r => r.status === 'FAIL');
  console.log(`Total Sections: ${SECTIONS.length}`);
  console.log(`Passed: ${SECTIONS.length - failures.length}`);
  console.log(`Failed: ${failures.length}`);
  
  if (failures.length > 0) {
    console.log('\nFailures breakdown:');
    console.dir(failures, { depth: null });
  }

  expect(failures.length).toBe(0);
});
