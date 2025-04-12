import React, { useEffect, useRef, useState } from "react";
import { CandlestickSeries, createChart, CrosshairMode, LineStyle } from "lightweight-charts";
import { useParams } from "react-router-dom";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";
import Transaction from "./Transaction";
import Navbar from "../Components/Navbar";
import { Container, Row, Col, Form } from "react-bootstrap";

function ChartPage() {
  const { Ticker } = useParams();
  const containerRef = useRef(null);
  const chartRef = useRef(null);
  const candlestickSeriesRef = useRef(null);
  const [rawData, setRawData] = useState([]);
  const [chartData, setChartData] = useState([]);
  const [timeframe, setTimeframe] = useState("1m");
  const [selectedTicker, setSelectedTicker] = useState(Ticker);
  const [stockInfo, setStockInfo] = useState(null);
  const [isFetching, setIsFetching] = useState(false);
  const [currentStartTime, setCurrentStartTime] = useState(null);
  const fetchedRanges = useRef(new Set());

  const aggregationIntervals = {
    "1m": 60, "5m": 300, "30m": 1800, "1h": 3600, "4h": 14400
  };

  const formatDate = (date) => date.toISOString().split("T")[0];

  const fetchHistoricalData = async (startTime = null) => {
    if (!selectedTicker || isFetching) return;

    let finalEndTime = new Date();
    let finalStartTime = new Date(finalEndTime.getTime() - 5 * 24 * 60 * 60 * 1000);

    if (startTime) {
      finalEndTime = new Date(startTime);
      finalStartTime = new Date(finalEndTime.getTime() - 5 * 24 * 60 * 60 * 1000);
    }

    const formattedStart = formatDate(finalStartTime);
    const formattedEnd = formatDate(finalEndTime);
    const rangeKey = `${formattedStart}_${formattedEnd}`;

    if (fetchedRanges.current.has(rangeKey)) return;

    setIsFetching(true);

    try {
      const response = await axios.post(getBackendURL(), {
        type: "FETCH_SPECIFIC_STOCK_DATA",
        ticker: selectedTicker,
        startTime: formattedStart,
        endTime: formattedEnd
      }, { withCredentials: true });

      if (response.status === 200 && response.data) {
        const stockInfo = response.data.stockInfo;
        let newData = response.data.chartData.map(item => ({
          time: parseInt(item.timestamp, 10),
          open: parseFloat(item.open),
          high: parseFloat(item.high),
          low: parseFloat(item.low),
          close: parseFloat(item.close),
          volume: item.volume ? parseInt(item.volume, 10) : 0,
        }));

        const uniqueData = Array.from(new Map(newData.map(item => [item.time, item])).values());
        uniqueData.sort((a, b) => a.time - b.time);

        setStockInfo(stockInfo);
        setRawData((prev) => [...uniqueData, ...prev]);
        fetchedRanges.current.add(rangeKey);
        setCurrentStartTime(formatDate(new Date(finalStartTime.getTime() - 24 * 60 * 60 * 1000)));
      }
    } catch (error) {
      console.error("Error fetching historical data:", error);
    }

    setIsFetching(false);
  };

  const aggregateData = (data, timeframe) => {
    const interval = aggregationIntervals[timeframe];
    const aggregated = [];
    let currentCandle = null;

    data.forEach(item => {
      const timestamp = Math.floor(item.time / interval) * interval;

      if (!currentCandle || currentCandle.time !== timestamp) {
        if (currentCandle) aggregated.push(currentCandle);
        currentCandle = {
          time: timestamp,
          open: item.open,
          high: item.high,
          low: item.low,
          close: item.close,
          volume: item.volume
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

  useEffect(() => {
    if (rawData.length === 0) return;
    const aggregatedData = aggregateData(rawData, timeframe);
    setChartData(aggregatedData);
  }, [timeframe, rawData]);

  useEffect(() => {
    if (!containerRef.current) return;

    const chart = createChart(containerRef.current, {
      width: containerRef.current.clientWidth,
      height: containerRef.current.clientHeight,
    });

    chart.applyOptions({
      layout: { attributionLogo: false },
      crosshair: {
        mode: CrosshairMode.Normal,
        vertLine: {
          width: 2, color: "#C3BCDB44", style: LineStyle.Solid,
          labelBackgroundColor: "#FFFFFF"
        },
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
    candlestickSeriesRef.current = seriesInstance;

    const handleScroll = () => {
      if (!chartRef.current || !candlestickSeriesRef.current || rawData.length === 0 || isFetching) return;
      if (!chartRef.current.timeScale()) return;

      const visibleRange = chartRef.current.timeScale().getVisibleRange();
      if (!visibleRange || !visibleRange.from) return;

      const firstDataPoint = rawData[0]?.time;
      const leftEdgeTimestamp = visibleRange.from;
      const THRESHOLD_SECONDS = aggregationIntervals[timeframe] * 2;

      if (leftEdgeTimestamp <= firstDataPoint + THRESHOLD_SECONDS) {
        fetchHistoricalData(currentStartTime);
      }
    };

    chart.timeScale().subscribeVisibleLogicalRangeChange(handleScroll);

    return () => {
      if (chartRef.current) {
        chart.timeScale().unsubscribeVisibleLogicalRangeChange(handleScroll);
        chartRef.current.remove();
        chartRef.current = null;
        candlestickSeriesRef.current = null;
      }
    };
  }, [chartData]);

  useEffect(() => {
    if (selectedTicker) fetchHistoricalData();
  }, [selectedTicker]);

  useEffect(() => {
    if (!candlestickSeriesRef.current || chartData.length === 0) return;
    candlestickSeriesRef.current.setData(chartData);
  }, [chartData]);

  return (
    <>
      <Navbar handleLogout={() => (window.location.href = "/")} />
      <Container fluid className="mt-4 bg-transparent px-3">
        <Row>
          <Col xs={12} lg={8}>
            <div className="position-relative bg-dark rounded p-3" style={{ height: "75vh" }}>
              <div className="position-absolute top-0 start-0 m-3">
                <Form.Select
                  value={timeframe}
                  onChange={(e) => setTimeframe(e.target.value)}
                  size="sm"
                  className="w-auto"
                >
                  <option value="1m">1 Minute</option>
                  <option value="5m">5 Minutes</option>
                  <option value="30m">30 Minutes</option>
                  <option value="1h">1 Hour</option>
                  <option value="4h">4 Hour</option>
                </Form.Select>
              </div>
              <div ref={containerRef} style={{ width: "100%", height: "100%" }} />
            </div>
          </Col>
          <Col xs={12} lg={4} className="mt-4 mt-lg-0">
            {stockInfo && <Transaction stockData={stockInfo} chartData={chartData} />}
          </Col>
        </Row>
      </Container>
    </>
  );
}

export default ChartPage;
