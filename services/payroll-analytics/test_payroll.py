import pytest
from src.payroll_engine import calculate_nssf, calculate_paye, calculate_lst

def test_nssf():
    res = calculate_nssf(1000000)
    assert res['employee_deduction'] == 50000
    assert res['employer_contribution'] == 100000
    assert res['total_contribution'] == 150000

def test_paye_exempt():
    assert calculate_paye(200000) == 0.0

def test_paye_band1():
    # 300,000 is 65k over 235k. 10% of 65k is 6500.
    assert calculate_paye(300000) == 6500.0

def test_paye_band2():
    # 400,000 is 65k over 335k. 20% of 65k is 13k + 10k = 23k.
    assert calculate_paye(400000) == 23000.0

def test_paye_band3():
    # 5,000,000. 5M - 410k = 4,590,000. 30% = 1,377,000 + 25k = 1,402,000.
    assert calculate_paye(5000000) == 1402000.0

def test_paye_band4():
    # 15,000,000. 15M - 10M = 5M. 40% = 2M + 2,902,000 = 4,902,000.
    assert calculate_paye(15000000) == 4902000.0

def test_lst_brackets():
    assert calculate_lst(80000) == 0.0
    assert calculate_lst(150000) == 5000.0
    assert calculate_lst(550000) == 40000.0
    assert calculate_lst(2000000) == 100000.0
