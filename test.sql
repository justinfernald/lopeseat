SELECT
    Orders.id,
    UNIX_TIMESTAMP(LatestDelivererRequest.time_max) * 1000 AS timeRequested,
    Restaurants.name AS restaurantName
FROM
    (
    SELECT
        order_id,
        MAX(time_created) AS time_max,
        status_id
    FROM
        `DelivererRequest`
    WHERE
        deliverer_id = 67
    GROUP BY
        order_id
) AS LatestDelivererRequest
INNER JOIN Orders ON LatestDelivererRequest.order_id = Orders.id
INNER JOIN OrderItems ON LatestDelivererRequest.order_id = OrderItems.order_id
INNER JOIN MenuItems ON OrderItems.item_id = MenuItems.id
INNER JOIN Restaurants ON MenuItems.restaurant_id = Restaurants.id
WHERE
    LatestDelivererRequest.status_id = 1