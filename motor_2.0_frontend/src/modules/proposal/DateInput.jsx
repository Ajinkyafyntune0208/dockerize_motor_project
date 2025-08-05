import React, { useState } from "react";
import DatePicker from "react-datepicker";
import { range } from "lodash";
import { getYear, getMonth } from "date-fns";
import "react-datepicker/dist/react-datepicker.css";
import "./date-picker.scss";
import moment from "moment";
import { RxCrossCircled } from "react-icons/rx";

const DateInputTwo = ({
  onChange,
  value,
  minDate,
  maxDate,
  name,
  ref,
  readOnly,
  reviewDate,
  showMonthYearPicker,
  expiryDate,
  id,
  autoFocus,
  filterDate,
  onSubmit,
  monthsShown,
  selected,
  dob,
  incorporation,
  errors,
}) => {
  const [startDate, setStartDate] = useState("");
  const years = range(
    dob ? 1922 : incorporation ? 1850 : 1940,
    getYear(new Date()) + 6,
    1
  );
  const months = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  return (
    <>
      {!monthsShown ? (
        <>
          <DatePicker
            className={`date ${reviewDate ? "curvedDate" : ""} ${
              expiryDate ? "expiryDate" : ""
            } ${filterDate ? "filterDate" : ""}`}
            showPopperArrow={false}
            renderCustomHeader={({
              date,
              changeYear,
              changeMonth,
              decreaseMonth,
              increaseMonth,
              prevMonthButtonDisabled,
              nextMonthButtonDisabled,
            }) => (
              <div
                className="date-header"
                style={{
                  display: "flex",
                  justifyContent: "center",
                }}
              >
                <button
                  className="date-button-left"
                  type="button"
                  onClick={decreaseMonth}
                  disabled={prevMonthButtonDisabled}
                >
                  {"<<"}
                </button>
                <select
                  id="year_dd"
                  className="year_dd"
                  value={getYear(date)}
                  onChange={({ target: { value } }) => changeYear(value)}
                >
                  {years.map((option) => (
                    <option id="option" key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>

                <select
                  id="month_dd"
                  className="month_dd"
                  value={months[getMonth(date)]}
                  onChange={({ target: { value } }) =>
                    changeMonth(months.indexOf(value))
                  }
                >
                  {months.map((option) => (
                    <option id={option} key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>

                <button
                  className="date-button-right"
                  type="button"
                  onClick={increaseMonth}
                  disabled={nextMonthButtonDisabled}
                >
                  {">>"}
                </button>
              </div>
            )}
            dateFormat="dd/MM/yyyy"
            showMonthYearPicker={showMonthYearPicker}
            onChange={(date) => {
              setStartDate(date);
              onChange(
                showMonthYearPicker
                  ? moment(date).format("MM-YYYY")
                  : moment(date).format("DD-MM-YYYY")
              );

              onSubmit && onSubmit(moment(date).format("DD-MM-YYYY"));
            }}
            selected={selected ? selected : startDate}
            value={value || ""}
            autoComplete="off"
            minDate={minDate}
            maxDate={maxDate}
            name={name || "date"}
            ref={ref}
            onFocus={(e) => e.target.blur()} // <--- Adding this
            autocomplete="off"
            readOnly={readOnly ? true : false}
            id={id}
            autoFocus={autoFocus}
          />
          {!!errors && value ? (
            <RxCrossCircled
              className="date-button-clear"
              type="button"
              onClick={() => {
                setStartDate("");
                onChange("");
              }}
              style={{
                position: "absolute",
                right: "45px",
                top: "53%",
                transform: "translateY(-50%)",
                cursor: "pointer",
              }}
            />
          ) : !readOnly && value ? (
            <RxCrossCircled
              className="date-button-clear"
              type="button"
              onClick={() => {
                setStartDate("");
                onChange("");
              }}
              style={{
                position: "absolute",
                right: "45px",
                top: "66.7%",
                transform: "translateY(-50%)",
                cursor: "pointer",
              }}
            />
          ) : (
            ""
          )}
        </>
      ) : (
        <>
          <DatePicker
            className={`date ${reviewDate ? "curvedDate" : ""} ${
              expiryDate ? "expiryDate" : ""
            } ${filterDate ? "filterDate" : ""}`}
            selected={selected ? selected : startDate}
            onChange={(date) => {
              setStartDate(date);
              onChange(
                showMonthYearPicker
                  ? moment(date).format("MM-YYYY")
                  : moment(date).format("DD-MM-YYYY")
              );

              onSubmit && onSubmit(moment(date).format("DD-MM-YYYY"));
            }}
            monthsShown={monthsShown}
            dateFormat={"dd-MM-yyyy"}
            showYearDropdownvalue={value || ""}
            autoComplete="off"
            minDate={minDate}
            maxDate={maxDate}
            name={name || "date"}
            ref={ref}
            autocomplete="off"
            readOnly={readOnly ? true : false}
            id={id}
            onFocus={(e) => e.target.blur()} // <--- Adding this
            autoFocus={autoFocus}
            showMonthYearPicker={showMonthYearPicker}
            showPopperArrow={false}
          />
        </>
      )}
    </>
  );
};

export default DateInputTwo;
