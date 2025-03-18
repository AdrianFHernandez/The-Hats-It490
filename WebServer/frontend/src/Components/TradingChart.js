import React, { useEffect, useRef, useState} from "react";
import { createChart, CrosshairMode, LineStyle, CandlestickSeries } from "lightweight-charts";
import Transaction from "./Transaction";
import Portfolio from "./Portfolio";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function TradingChart({ Ticker }) {
    const containerRef = useRef(null);
    const chartRef = useRef(null);
    const [chartData, setChartData] = useState([]);
    const [candlestickSeries, setCandlestickSeries] = useState(null);
    const [userAccount, setUserAccount] = useState(null);
    const [timeframe, setTimeframe] = useState("1m");
    const [remainingData, setRemainingData] = useState([]);
    const [fetchingMoreData, setFetchingMoreData] = useState(false);
    const [selectedTicker, setSelectedTicker] = useState(Ticker);
    const [startTime, setStartTime] = useState(null);
    const [endTime, setEndTime] = useState(null);

    const delaySeconds = 86400; // Delay in displaying data, not fetching
    const aggregationIntervals = {
        "1m": 60, "5m": 300, "30m": 1800, "1h": 3600, "1d": 86400
    };

    const convertUtcToLocal = (utcTimestamp) => Math.floor(new Date(utcTimestamp * 1000).getTime() / 1000);

    const aggregateData = (data, timeframe) => {
        const interval = aggregationIntervals[timeframe];
        const aggregated = [];
        let currentCandle = null;

        data.forEach(item => {
            const timestamp = Math.floor(item.time / interval) * interval;
            if (!currentCandle || currentCandle.time !== timestamp) {
                if (currentCandle) aggregated.push(currentCandle);
                currentCandle = { time: timestamp, open: item.open, high: item.high, low: item.low, close: item.close, volume: item.volume };
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

    const fetchHistoricalData = async (newStartTime = null, newEndTime = null) => {
        if (!selectedTicker) return;

        try {
            setFetchingMoreData(true);

            const today = new Date();
            const finalEndTime = newEndTime || today;
            const finalStartTime = newStartTime || new Date(finalEndTime);
            finalStartTime.setDate(finalEndTime.getDate() - 5);

            setStartTime(finalStartTime);
            setEndTime(finalEndTime);

            const formattedStart = finalStartTime.toISOString().split("T")[0];
            const formattedEnd = finalEndTime.toISOString().split("T")[0];

            console.log(`Fetching historical data for ${selectedTicker} from ${formattedStart} to ${formattedEnd}`);

            const response = await axios.post(
                getBackendURL(),
                { type: "FETCH_SPECIFIC_STOCK_DATA", ticker: selectedTicker, startTime: formattedStart, endTime: formattedEnd },
                { withCredentials: true }
            );

            if (response.status === 200 && response.data) {
                console.log("Fetched historical data:", response.data);
                let newData = response.data.chartData.map(item => ({
                    time: item.timestamp,
                    open: parseFloat(item.open),
                    high: parseFloat(item.high),
                    low: parseFloat(item.low),
                    close: parseFloat(item.close),
                    volume: item.volume ? parseInt(item.volume, 10) : 0
                }));

                newData.sort((a, b) => a.time - b.time);

                if (newStartTime) {
                    setChartData(prevData => [...newData, ...prevData]);
                } else {
                    setChartData(newData);
                }

                setRemainingData(newData);
                chartRef.current.timeScale().fitContent();
            }
        } catch (error) {
            console.error("Error fetching historical data:", error);
        } finally {
            setFetchingMoreData(false);
        }
    };

    useEffect(() => {
        fetchHistoricalData();

    }, [selectedTicker]);

    useEffect(() => {
        if (!candlestickSeries || remainingData.length === 0) return;

        const updateInterval = setInterval(() => {
            const nowLocal = Math.floor(Date.now() / 1000);
            const simulatedTime = nowLocal - delaySeconds; // Displaying data as if it were "live"

            if (remainingData.length > 0 && remainingData[0].time <= simulatedTime) {
                const nextData = remainingData.shift();
                setRemainingData([...remainingData]);
                console.log("Simulated real-time update:", nextData);
                candlestickSeries.update(nextData);
                setChartData(prevData => [...prevData, nextData]);
            }
        }, 60000); // Update every minute

        return () => clearInterval(updateInterval);
    }, [candlestickSeries, remainingData]);

    useEffect(() => {
        if (!containerRef.current) return;
        const container = containerRef.current;
        
        const chart = createChart(container, {
            width: container.clientWidth,
            height: container.clientHeight,
        });

        chart.applyOptions({
            layout: { attributionLogo: false },
            crosshair: {
                mode: CrosshairMode.Normal,
                vertLine: { width: 2, color: "#C3BCDB44", style: LineStyle.Solid, labelBackgroundColor: "#FFFFFF" },
            },
            timeScale: { timeVisible: true, secondsVisible: false, rightOffset: 5 },
        });

        const seriesInstance = chart.addSeries(CandlestickSeries, {
          upColor: "#26a69a",
          downColor: "#ef5350",
          borderVisible: false,
          wickUpColor: "#26a69a",
          wickDownColor: "#ef5350",
        });

        chartRef.current = chart;
        setCandlestickSeries(seriesInstance);
    }, []);

    useEffect(() => {
        if (!candlestickSeries || chartData.length === 0) return;
        const aggregatedData = aggregateData(chartData, timeframe);
        candlestickSeries.setData(aggregatedData);
    }, [timeframe, chartData, candlestickSeries]);

    const handleTickerSelect = (ticker) => {
        if (ticker === selectedTicker) return;
        console.log(`Switching to ticker: ${ticker}`);
        setSelectedTicker(ticker);
    };

    return (
        <div className="ChartContainer" style={{ width: "95vw", height: "90vh", backgroundColor: "teal", position: "relative", padding: "2rem", display: "flex", flexDirection: "row" }}>
            <div className="TradingChart" ref={containerRef} style={{ width: "80%", height: "90%", position: "relative" }} />
            <div style={{ position: "absolute", top: "0px", left: "20px" }}>
                <select value={timeframe} onChange={e => setTimeframe(e.target.value)} style={{ padding: "5px", fontSize: "14px", borderRadius: "5px", border: "1px solid #ccc", backgroundColor: "#fff", cursor: "pointer" }}>
                    <option value="1m">1 Minute</option>
                    <option value="5m">5 Minutes</option>
                    <option value="30m">30 Minutes</option>
                    <option value="1h">1 Hour</option>
                    <option value="1d">Daily</option>
                </select>
            </div>

            <div style={{ display:"flex", flexDirection:"column", justifyContent:"center", alignItems:"center" }}>
                {/* {userAccount && <Portfolio userAccount={userAccount} onSelectTicker={handleTickerSelect} />} */}
                {/* <Transaction stockData={{ ticker: selectedTicker }} chartData={chartData} /> */}
            </div>
        </div>
    );
}

export default TradingChart;
