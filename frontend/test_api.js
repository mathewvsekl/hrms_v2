import fs from 'fs';
import axios from 'axios';

async function check() {
  const token = '5adb1910ccd3ab4caf81ab6c820998e0331bfa0c8eb88e35a5f1ec8b2f20e9f8';
  try {
    const res = await axios.get('http://localhost:8000/api/employees?company_id=1&limit=1000', {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    console.log(JSON.stringify(res.data).substring(0, 500));
  } catch (e) {
    console.error(e.message);
  }
}

check();
