import React, { useMemo } from "react";
import { PieChart, Pie, Cell, Tooltip, Legend } from "recharts";

const StockDiversityChart = ({ userStocks }) => {
  console.log("Received userStocks in StockDiversityChart:", userStocks);

  if (!userStocks || Object.keys(userStocks).length === 0) {
    return <p>No stocks owned.</p>;
  }

  const stockEntries = Object.entries(userStocks).map(([ticker, stock]) => ({
    name: stock.companyName || ticker, // Ensure name exists
    value: stock.count > 0 ? stock.count : 1, // Ensure value is never 0 or null
  }));
  

  console.log("Processed stockEntries:", stockEntries);

  const COLORS = ["#0088FE", "#00C49F", "#FFBB28", "#FF8042"];

  return (
    <div style={{ textAlign: "center" }}>
      <h2>Stock Diversity</h2>
      <PieChart width={400} height={400}>
      <Pie
            data={stockEntries}
            dataKey="value"  // This should match the processed data structure
            cx="50%"
            cy="50%"
            outerRadius={120}
            fill="#8884d8"
            // label={({ name, percent }) => `${name} (${(percent * 100).toFixed(1)}%)`}
        >
          {stockEntries.map((entry, index) => (
            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
          ))}
        </Pie>
        <Tooltip />
        <Legend />
      </PieChart>
    </div>
  );
};

export default StockDiversityChart;
