import React, { useEffect, useRef, useState } from "react";
import { createChart, CrosshairMode, LineStyle, CandlestickSeries } from "lightweight-charts";
import Transaction from "./Transaction";
import Portfolio from "./Portfolio";

function TradingChart({ stockData }) {
  const containerRef = useRef(null);
  const chartRef = useRef(null);
  const [chartData, setChartData] = useState([]);
  const [candlestickSeries, setCandlestickSeries] = useState(null);
  const [series, setSeries] = useState(null);
  const [userAccount, setUserAccount] = useState(null);
  const [timeframe, setTimeframe] = useState("1m"); // Default timeframe
  const [remainingData, setRemainingData] = useState([]); // Future (delayed) data waiting in the wings
  const [fetchingMoreData, setFetchingMoreData] = useState(false);
  const [selectedTicker, setSelectedTicker] = useState("TSLA"); // Default ticker
  
  // Define your delay in seconds (24 hours = 86400 seconds)
  const delaySeconds = 86400;

  const chartOptions = {
    layout: { attributionLogo: false },
    crosshair: {
      mode: CrosshairMode.Normal,
      vertLine: {
        width: 2,
        color: "#C3BCDB44",
        style: LineStyle.Solid,
        labelBackgroundColor: "#FFFFFF",
      },
    },
    timeScale: {
      timeVisible: true,
      secondsVisible: false,
      rightOffset: 5,
      fixLeftEdge: false,
      fixRightEdge: false,
      borderVisible: false,
    },
  };

  const aggregationIntervals = {
    "1m": 60,      // 1 minute
    "5m": 300,     // 5 minutes
    "30m": 1800,   // 30 minutes
    "1h": 3600,    // 1 hour
    "1d": 86400,   // 1 day
  };

  const convertUtcToLocal = (utcTimestamp) => {
    return Math.floor(new Date(utcTimestamp * 1000).getTime() / 1000);
  };

  const aggregateData = (data, timeframe) => {
    const interval = aggregationIntervals[timeframe];
    const aggregated = [];
    let currentCandle = null;

    data.forEach(item => {
      const timestamp = Math.floor(item.time / interval) * interval; // Normalize time

      if (!currentCandle || currentCandle.time !== timestamp) {
        if (currentCandle) aggregated.push(currentCandle);
        currentCandle = {
          time: timestamp,
          open: item.open,
          high: item.high,
          low: item.low,
          close: item.close,
          volume: item.volume,
        };
      } else {
        currentCandle.high = Math.max(currentCandle.high, item.high);
        currentCandle.low = Math.min(currentCandle.low, item.low);
        currentCandle.close = item.close;
        currentCandle.volume += item.volume;
      }
    });

    if (currentCandle) aggregated.push(currentCandle);
    return aggregated;
  };

  const fetchMoreHistoricalData = async (oldestTimestamp) => {
    if (fetchingMoreData) return;
    setFetchingMoreData(true);
  
    try {
      console.log("Fetching more data before:", new Date(oldestTimestamp * 1000));
  
      // Fetch more historical data for the selected ticker
      const response = await fetch(`/Meta2.json?ticker=${selectedTicker}&before=${oldestTimestamp}`);
      const data = await response.json();
      
      if (data.length === 0) {
        console.log("No more historical data available.");
        setFetchingMoreData(false);
        return;
      }
  
      let newData = data.map(item => ({
        time: convertUtcToLocal(Math.floor(new Date(item.t).getTime() / 1000)),
        open: item.o,
        high: item.h,
        low: item.l,
        close: item.c,
        volume: item.v || 0,
      }));
  
      // Ensure newData is sorted in ascending order before merging
      newData.sort((a, b) => a.time - b.time);
  
      // Merge old and new data, ensuring the final dataset remains sorted
      setChartData(prevData => {
        const mergedData = [...newData, ...prevData].sort((a, b) => a.time - b.time);
        console.log("Merged Data First Entry:", mergedData[0]);
        console.log("Merged Data Last Entry:", mergedData[mergedData.length - 1]);
        return mergedData;
      });
  
      setFetchingMoreData(false);
    } catch (error) {
      console.error("Error fetching more historical data:", error);
      setFetchingMoreData(false);
    }
  };

  // Dummy user account rn
  useEffect(() => {
    setUserAccount({
      "userStocks" : {
        "TSLA": {
          "companyName" : "Tesla",
          "companyDescription": "This company does this ...",
          "count" : 2,
          "averagePrice" : 300
        },
        "VOO" : {
          "companyName" : "Vanguard",
          "count" : 1,
          "averagePrice" : 390
        }
      },
      "userBalance": {
        "buyingPower": 10, 
        "stockBalance": 990, 
        "totalBalance" : 1000
      }
    });
  }, []);

  // Fetch data whenever the selected ticker changes.
  useEffect(() => {
    const fetchData = async () => {
      try {
        // Clear current data on ticker change
        setChartData([]);
        setRemainingData([]);
        console.log("FEtching " + `${selectedTicker}.json`)
        const response = await fetch(`${selectedTicker}.json`);
        const data = await response.json();
      
        const nowLocal = Math.floor(Date.now() / 1000);
        const latestAllowedTime = nowLocal - delaySeconds;
  
        // Convert UTC timestamps to local and filter based on the delay
        const formattedData = data.map(item => ({
          time: convertUtcToLocal(Math.floor(new Date(item.t).getTime() / 1000)),
          open: item.o,
          high: item.h,
          low: item.l,
          close: item.c,
          volume: item.v || 0,
        }));
  
        // Split data into historical (pastData) and future (delayed) segments
        const pastData = formattedData.filter(item => item.time <= latestAllowedTime);
        const futureData = formattedData.filter(item => item.time > latestAllowedTime);
  
        setChartData(pastData);
        setRemainingData(futureData);
      } catch (error) {
        console.error("Error fetching data:", error);
      }
    };

    fetchData();
  }, [selectedTicker]);

  // Listen for changes in the visible range to fetch more historical data
  useEffect(() => {
    if (!chartRef.current) return;
    const chart = chartRef.current;
    const timeScale = chart.timeScale();

    const handleVisibleRangeChange = () => {
      const visibleRange = timeScale.getVisibleRange();
      if (!visibleRange) return;

      const oldestTimestamp = chartData[0]?.time;
      const totalRange = visibleRange.to - visibleRange.from;
      const remainingTime = visibleRange.from - oldestTimestamp;

      if (remainingTime / totalRange < 0.05 && oldestTimestamp > new Date("2025-01-12").getTime() / 1000) {
        fetchMoreHistoricalData(oldestTimestamp);
      }
    };

    timeScale.subscribeVisibleTimeRangeChange(handleVisibleRangeChange);

    return () => {
      timeScale.unsubscribeVisibleTimeRangeChange(handleVisibleRangeChange);
    };
  }, [chartData, selectedTicker]);

  // Initialize the chart on mount
  useEffect(() => {
    if (!containerRef.current) return;
    const container = containerRef.current;
    
    const chart = createChart(container, {
      width: container.clientWidth,
      height: container.clientHeight,
    });
    chart.applyOptions(chartOptions);

    const seriesInstance = chart.addSeries(CandlestickSeries, {
      upColor: "#26a69a",
      downColor: "#ef5350",
      borderVisible: false,
      wickUpColor: "#26a69a",
      wickDownColor: "#ef5350",
    });
    
    chartRef.current = chart;
    setCandlestickSeries(seriesInstance);
    setSeries(seriesInstance);
  }, []);

  // Update chart data when timeframe or chartData changes
  useEffect(() => {
    if (!candlestickSeries || chartData.length === 0) return;
    console.log(`Updating Chart for Timeframe: ${timeframe}`);
    const aggregatedData = aggregateData(chartData, timeframe);
    candlestickSeries.setData(aggregatedData);
  }, [timeframe, chartData, candlestickSeries]);

  // Update the chart with new delayed data based on local time
  useEffect(() => {
    if (!candlestickSeries || remainingData.length === 0) return;

    const updateInterval = setInterval(() => {
      const nowLocal = Math.floor(Date.now() / 1000);
      
      // If the first item in the future data is now "due"
      if (remainingData.length > 0 && remainingData[0].time <= nowLocal - delaySeconds) {
        const nextData = remainingData.shift();
        setRemainingData([...remainingData]);
        
        console.log("Updating chart with new data:", nextData);
        candlestickSeries.update(nextData);
        setChartData(prevData => [...prevData, nextData]);
      }
    }, 60000); // Check every minute

    return () => clearInterval(updateInterval);
  }, [candlestickSeries, remainingData]);

  // Handle ticker selection from the Portfolio component
  const handleTickerSelect = (ticker) => {
    if (ticker === selectedTicker) return;
    console.log(`Switching to ticker: ${ticker}`);
    setSelectedTicker(ticker);
  };

  return (
    <div className="ChartContainer" style={{ width: "95vw", height: "90vh", backgroundColor: "teal", position: "relative", padding: "2rem", display: "flex", flexDirection: "row" }}>
      {/* Chart Container */}
      <div className="TradingChart" id="mainChart" ref={containerRef} style={{ width: "80%", height: "90%", position: "relative" }} />

      {/* Timeframe Selector */}
      <div style={{ position: "absolute", top: "0px", left: "20px" }}>
        <select
          value={timeframe}
          onChange={e => setTimeframe(e.target.value)}
          style={{
            padding: "5px",
            fontSize: "14px",
            borderRadius: "5px",
            border: "1px solid #ccc",
            backgroundColor: "#fff",
            cursor: "pointer",
          }}
        >
          <option value="1m">1 Minute</option>
          <option value="5m">5 Minutes</option>
          <option value="30m">30 Minutes</option>
          <option value="1h">1 Hour</option>
          <option value="1d">Daily</option>
        </select>
      </div>

      <div style={{ display:"flex", flexDirection:"column", justifyContent:"center", alignItems:"center" }}>
        {userAccount && (
          <Portfolio 
            userAccount={userAccount} 
            onSelectTicker={handleTickerSelect} // Callback for dynamic ticker switching
          />
        )}
        <Transaction 
          stockData={stockData} 
          chartData={chartData} 
          onTransaction={(type, amount, qty) => { console.log(amount, type, qty) }} 
        />
      </div>
    </div>
  );
}

export default TradingChart;
