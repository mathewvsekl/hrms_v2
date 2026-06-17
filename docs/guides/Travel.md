**Outstation \+ Overseas travel, configurable workflows, day-wise itinerary, attendance integration, TOIL (ledger \+ profile sync), multi-country logic, and a travel calendar.**

---

**🧭 1\. System Overview (What You’re Building)**

A **Global Travel & Duty Management Engine** integrated with:

* Attendance  
* TOIL (Time Off In Lieu)  
* Multi-country policies  
* Configurable workflows  
* Visual calendar

---

**🧩 2\. Core Modules**

**1\. Travel Request Module**

**2\. Workflow Engine (Configurable)**

**3\. Itinerary Engine (Day-wise classification)**

**4\. Attendance Engine**

**5\. TOIL Engine \+ Ledger**

**6\. Employee Profile Sync**

**7\. Multi-Country Policy Engine**

**8\. Travel Calendar**

---

**🌍 3\. Master Data Setup**

**Offices (Multi-country foundation)**

office\_locations  
 \- id  
 \- name  
 \- country\_code  
 \- city  
 \- timezone  
 \- holiday\_calendar\_id  
 \- weekend\_policy\_id  
 \- currency

---

**Travel Categories**

* OUTSTATION  
* OVERSEAS

---

**Travel Purpose**

* TRAINING  
* CUSTOMER\_SERVICE  
* BUSINESS\_MEETING

---

**Itinerary Types (Configurable)**

* TRANSIT  
* BUSINESS  
* WEEKEND  
* HOLIDAY

Sub-types:

* TRAINING / SERVICE / MEETING

---

**🔄 4\. Travel Request Lifecycle**

Draft → Submitted → Under Review → Reviewed → Approved → Completed

**Workflow is configurable per:**

* Travel Purpose  
* Travel Category  
* Country

---

**⚙️ 5\. Configurable Workflow Engine**

**Tables**

travel\_status\_master  
 travel\_workflow\_config  
 approval\_matrix

---

**Example**

**Customer Service (Fast)**

Submitted → Manager → Approved

**Overseas (Strict)**

Submitted → Manager → HR → Finance → Approved

---

**🧾 6\. Travel Request Structure**

travel\_requests  
 \- employee\_id  
 \- origin\_office\_id  
 \- destination\_office\_id  
 \- travel\_category  
 \- travel\_type  
 \- from\_datetime  
 \- to\_datetime  
 \- purpose  
 \- status

---

**🗓️ 7\. Itinerary Engine (CORE INTELLIGENCE)**

**Table**

travel\_itinerary  
 \- travel\_id  
 \- date  
 \- itinerary\_type  
 \- sub\_type  
 \- start\_time  
 \- end\_time  
 \- country\_context

---

**Capabilities**

✅ Multi-entry per day  
 ✅ Auto-fill:

* Weekends  
* Holidays (based on destination country)

---

**Example**

| Date | Type | Sub-type |
| :---- | :---- | :---- |
| Day 1 | Transit | — |
| Day 2 | Business | Service |
| Day 3 | Weekend | — |
| Day 4 | Holiday | — |

---

**🌐 8\. Multi-Country Logic**

**Rule:**

During travel → **Destination country rules apply**

---

**Applies to:**

* Holidays  
* Weekends  
* TOIL eligibility  
* Attendance

---

**Tables**

holiday\_calendar  
 holidays  
 weekend\_policy

---

**⏱️ 9\. Attendance Engine**

**Table**

attendance  
 \- employee\_id  
 \- date  
 \- status \= TR  
 \- sub\_status \= TRANSIT / TRAINING / SERVICE / MEETING / WEEKEND / HOLIDAY  
 \- source\_id (travel\_id)

---

**Rules**

* Approved travel → override attendance  
* Multi-day → continuous TR  
* Split-day supported

---

**⏱️ 10\. TOIL Engine (Highly Structured)**

**TOIL Rules**

| Scenario | TOIL |
| :---- | :---- |
| Weekend travel | Full day |
| Holiday work | Full day |
| Transit (long hours) | Based on hours |
| Business overtime | Partial |

---

**TOIL Ledger**

toil\_ledger  
 \- employee\_id  
 \- source\_type (travel)  
 \- source\_id  
 \- source\_subtype  
 \- country\_context  
 \- days\_earned  
 \- hours\_earned  
 \- expiry\_date  
 \- status

---

**👤 11\. Employee Profile Integration**

**Profile Fields**

employee\_profile  
 \- toil\_balance\_days  
 \- toil\_balance\_hours  
 \- toil\_expiring\_soon

---

**Logic**

* Auto-update after:  
  * TOIL earned  
  * TOIL used  
  * TOIL expired

👉 Balance \= **Derived from ledger (not manual)**

---

**Country-wise TOIL**

employee\_toil\_summary  
 \- employee\_id  
 \- country\_code  
 \- toil\_days

---

**🔁 12\. Reversal & Audit**

If travel:

* Cancelled  
* Rejected  
* Modified

👉 System:

* Reverses TOIL  
* Updates attendance  
* Logs audit trail

---

**📅 13\. Travel Calendar (Visualization Layer)**

**Views**

* Monthly  
* Weekly

---

**Display**

* Multi-day bars  
* Color by:  
  * Purpose  
  * Status

---

**Icons**

* ✈️ Transit  
* 🛠️ Service  
* 🎓 Training  
* 🤝 Meeting  
* 🏖️ Weekend  
* 🎉 Holiday

---

**Filters**

* Employee  
* Country  
* Travel type  
* Status

---

**🔔 14\. Notifications**

* Request submitted  
* Approval pending  
* Travel approved  
* TOIL earned  
* TOIL expiring

---

**📊 15\. Reports**

**HR**

* Travel days by type  
* TOIL liability  
* Country-wise travel

**Manager**

* Team travel calendar  
* Pending approvals

**Employee**

* Travel history  
* TOIL balance

---

**🔐 16\. Controls & Policies**

* Max travel duration  
* Mandatory itinerary  
* No overlap with leave  
* Document requirement (overseas)

---

**🧠 17\. System Design Principles (Critical)**

**1\. Ledger-Based TOIL**

Never store static balances

**2\. Configurable Workflows**

No hardcoding

**3\. Country-Aware Rules**

Everything depends on location

**4\. Itinerary-Driven Logic**

Attendance & TOIL derived from itinerary

---

**🧩 18\. Final Architecture**

Employee (Office \+ Country)  
     	↓  
 Travel Request (Origin → Destination)  
     	↓  
 Workflow Engine (Configurable)  
     	↓  
 Itinerary Engine (Day-wise \+ Country-aware)  
     	↓  
 Attendance Engine (TR \+ sub-status)  
     	↓  
 TOIL Engine (rule-based)  
     	↓  
 TOIL Ledger  
     	↓  
 Employee Profile (auto sync)  
     	↓  
 Calendar \+ Reports

---

**🚀 19\. Implementation**

* Travel request  
* Approval workflow  
* Office \+ country setup  
* Itinerary engine  
* Attendance integration  
* TOIL engine \+ ledger  
* Employee profile sync  
* Multi-country rules  
* Calendar  
* Reports

 

