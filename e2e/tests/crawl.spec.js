const { test, expect } = require('@playwright/test');

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

test('Test dashboard links and functionalities', async ({ page }) => {
  test.setTimeout(120000); // Increase timeout to 2 minutes
  const pageErrors = [];
  const networkErrors = [];

  // Listen for console errors
  page.on('console', msg => {
    if (msg.type() === 'error') {
      pageErrors.push(`[${page.url()}] Console Error: ${msg.text()}`);
    }
  });

  // Listen for failed API requests
  page.on('response', response => {
    if (response.status() >= 400 && response.url().includes('/api/')) {
      networkErrors.push(`[${page.url()}] API Error ${response.status()} on ${response.url()}`);
    }
  });

  console.log('Logging in and navigating to dashboard...');
  await page.goto('https://emm.anedins.com/dashboard');
  
  // Wait for the app to render and fetch initial data
  await page.waitForTimeout(3000);

  // Take a screenshot of the dashboard
  await page.screenshot({ path: 'dashboard.png' });

  // Extract all navigation links from the sidebar/dashboard
  const links = await page.$$eval('a', anchors => 
    anchors.map(a => a.href).filter(href => 
      href && 
      href.startsWith('https://emm.anedins.com/') && 
      !href.includes('logout') &&
      href !== 'https://emm.anedins.com/'
    )
  );
  
  const uniqueLinks = [...new Set(links)];
  console.log(`Found ${uniqueLinks.length} unique links to check.`);

  const brokenPages = [];

  for (const link of uniqueLinks) {
    console.log(`Checking link: ${link}`);
    await page.goto(link);
    await page.waitForTimeout(1500); // give the page time to load and trigger API requests
    
    const bodyText = await page.innerText('body');
    const is404 = bodyText.includes('404') && (bodyText.toLowerCase().includes('not found') || bodyText.toLowerCase().includes('oops'));
    
    if (is404) {
      brokenPages.push(link);
      console.log(`❌ Broken Page (404): ${link}`);
    } else {
      console.log(`✅ Loaded: ${link}`);
    }
  }

  console.log('\n=======================================');
  console.log('             TEST RESULTS              ');
  console.log('=======================================');
  console.log(`Total Links Checked: ${uniqueLinks.length}`);
  console.log(`Broken Pages (404): ${brokenPages.length}`);
  console.log(`API Network Errors: ${networkErrors.length}`);
  console.log(`Console Errors: ${pageErrors.length}`);

  if (brokenPages.length > 0) {
    console.log('\n--- BROKEN PAGES ---');
    console.table(brokenPages);
  }

  if (networkErrors.length > 0) {
    console.log('\n--- API NETWORK ERRORS ---');
    networkErrors.forEach(err => console.log(err));
  }

  // Generate an artifact report
  const report = {
    brokenPages,
    networkErrors,
    pageErrors
  };
  
  // Expose errors for the assertion
  expect(brokenPages.length, `Found ${brokenPages.length} broken links`).toBe(0);
});
