import React from "react";
import { PieChart, Pie, Cell, Tooltip, Legend } from "recharts";

const StockDiversityChart = ({ userStocks }) => {
  if (!userStocks || Object.keys(userStocks).length === 0) {
    return <p className="text-center">No stocks owned.</p>;
  }

  const stockEntries = Object.entries(userStocks).map(([ticker, stock]) => ({
    name: stock.companyName || ticker,
    value: stock.count > 0 ? stock.count : 1,
  }));

  const COLORS = ["#0088FE", "#00C49F", "#FFBB28", "#FF8042"];

  return (
    <div className="text-center">
      <h4 className="mb-4 text-light">Stock Diversity</h4>
      <div className="d-flex justify-content-center">
        <PieChart width={300} height={360}>
          <Pie
            data={stockEntries}
            dataKey="value"
            cx="50%"
            cy="40%"
            outerRadius={100}
            fill="#8884d8"
          >
            {stockEntries.map((entry, index) => (
              <Cell
                key={`cell-${index}`}
                fill={COLORS[index % COLORS.length]}
              />
            ))}
          </Pie>

          <Tooltip />

          <Legend verticalAlign="bottom" wrapperStyle={{ marginTop: 30 }} />
        </PieChart>
      </div>
    </div>
  );
};

export default StockDiversityChart;
