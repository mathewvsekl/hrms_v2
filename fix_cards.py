import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target = """                              <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                  <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total PAYE</p>
                                  <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}>{renderCurrency(totals.paye)}</h4>
                                  {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                      <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                          {selectedRun.reporting_currency} {(totals.paye / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                      </div>
                                  )}
                              </div>
                              <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                  <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total NSSF</p>
                                  <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}>{renderCurrency(totals.nssf)}</h4>
                                  {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                      <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                          {selectedRun.reporting_currency} {(totals.nssf / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                      </div>
                                  )}
                              </div>"""

replacement = """                              {isUganda && (
                                  <>
                                      <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                          <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total PAYE</p>
                                          <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}>{renderCurrency(totals.paye)}</h4>
                                          {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                              <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                                  {selectedRun.reporting_currency} {(totals.paye / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                              </div>
                                          )}
                                      </div>
                                      <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                          <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total NSSF</p>
                                          <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}>{renderCurrency(totals.nssf)}</h4>
                                          {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                              <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                                  {selectedRun.reporting_currency} {(totals.nssf / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                              </div>
                                          )}
                                      </div>
                                  </>
                              )}"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)

print("Wrapped summary cards with isUganda condition.")
