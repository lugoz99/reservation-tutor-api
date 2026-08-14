SELECT
    id,
    tutor_id,
    reservation_date,
    start_time,
    end_time,
    reservation_status,
    total_amount,
    payment_status
FROM reservations
WHERE tutor_id = 26
ORDER BY reservation_date, start_time;

SELECT *
FROM reservations
WHERE tutor_id = 26
  AND reservation_date = '2026-08-18'
  AND reservation_status IN ('pending', 'confirmed')
ORDER BY start_time;

--anthony.allen@example.com