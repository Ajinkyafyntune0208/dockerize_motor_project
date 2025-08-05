import React, { useState, forwardRef } from "react";
import DatePicker from "react-datepicker";
import { range } from "lodash";
import { getYear, getMonth } from "date-fns";
import "react-datepicker/dist/react-datepicker.css";
import "./date-picker.scss";
import moment from "moment";
import { Button } from "components";

const DateInputTwo = ({
  onChange,
  id,
  onInput,
  value,
  minDate,
  maxDate,
  name,
  ref,
  readOnly,
  monthsShown,
  showMonthYearPicker,
  dateFormat,
  yearsShown,
  disabled,
  rangeMin,
  rangeMax,
  customInput,
  reviewDate,
  onSubmit,
  btnText,
  autoFocus,
  selected,
  editPopupDate,
  onValueChange,
  withPortal,
  placeholderText,
  singleYear,
}) => {
  const [startDate, setStartDate] = useState(customInput ? new Date() : "");
  const years = singleYear
    ? [`20${singleYear}`]
    : range(
        rangeMin
          ? rangeMin
          : editPopupDate
          ? getYear(new Date()) - Number(25)
          : 1940,
        rangeMax
          ? rangeMax
          : getYear(new Date()) + Number(editPopupDate ? 1 : 5),
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

  //for Custom Input
  const CustomInput = forwardRef(({ value, onClick }, ref) => (
    <div style={{ maxWidth: "30px !important" }}>
      <Button
        id={"btnDate"}
        className="p-0 m-0"
        buttonStyle="outline-solid"
        borderRadius="2px"
        hex1={btnText ? "transparent" : "#fff"}
        hex2={btnText ? "transparent" : "#fff"}
        color={"#fff"}
        type="button"
        shadow={"none"}
        onClick={onClick}
        ref={ref}
      >
        {btnText ? (
          <p
            className="p-0 m-0"
            style={{ color: "rgb(10,63,3)", fontSize: "12px" }}
          >
            EDIT
          </p>
        ) : (
          <img
            src={`${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/cal-green.png`}
            alt="cal"
            height="23"
          />
        )}
      </Button>
    </div>
  ));

  // useEffect(() => {
  // 	if (selected) {
  // 		setStartDate(selected);
  // 	}
  // 	// eslint-disable-next-line react-hooks/exhaustive-deps
  // }, [selected]);

  return (
    <>
      {!monthsShown ? (
        <DatePicker
          id={id}
          // selected={startDate}

          selected={selected ? selected : startDate}
          // autoFocus={autoFocus}
          className={`date ${reviewDate ? "curvedDate" : ""} ${
            editPopupDate ? "curvedDate2" : ""
          } `}
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
                value={getYear(date)}
                onChange={({ target: { value } }) => changeYear(value)}
              >
                {years.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>

              <select
                value={months[getMonth(date)]}
                onChange={({ target: { value } }) =>
                  changeMonth(months.indexOf(value))
                }
              >
                {months.map((option) => (
                  <option key={option} value={option}>
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
          dateFormat={dateFormat || "dd/MM/yyyy"}
          onChange={(date) => {
            setStartDate(date);
            onChange(
              showMonthYearPicker
                ? moment(date).format("MM-YYYY")
                : moment(date).format("DD-MM-YYYY")
            );
            onSubmit &&
              onSubmit(
                showMonthYearPicker
                  ? { year: moment(date).format("MM-YYYY") }
                  : { year: moment(date).format("DD-MM-YYYY") }
              );
            onValueChange && onValueChange(date);
          }}
          value={value || ""}
          autoComplete="off"
          minDate={minDate}
          customInput={customInput ? <CustomInput /> : false}
          maxDate={maxDate}
          name={name || "date"}
          ref={ref}
          autocomplete="off"
          readOnly={readOnly ? true : false}
          monthsShown={monthsShown || 1}
          yearsShown={yearsShown || 1}
          showMonthYearPicker={showMonthYearPicker}
          disabled={disabled}
          onInput={onInput}
          popperPlacement={editPopupDate ? "bottom" : ""}
          onFocus={(e) => e.target.blur()} // <--- Adding this
          popperModifiers={{
            flip: {
              behavior: editPopupDate ? ["bottom"] : false, // don't allow it to flip to be above
            },
            preventOverflow: {
              enabled: false, // tell it not to try to stay within the view (this prevents the popper from covering the element you clicked)
            },
            hide: {
              enabled: false, // turn off since needs preventOverflow to be enabled
            },
          }}
          withPortal={withPortal}
          placeholderText={placeholderText}
        />
      ) : (
        <>
          <DatePicker
            className="date"
            selected={selected ? selected : startDate}
            onChange={(date) => {
              setStartDate(date);
              onChange(
                showMonthYearPicker
                  ? moment(date).format("MM-YYYY")
                  : moment(date).format("DD-MM-YYYY")
              );
              onSubmit(date);
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
            yearsShown={yearsShown || 1}
            disabled={disabled}
            onInput={onInput}
            popperPlacement={editPopupDate ? "bottom" : ""}
            onFocus={(e) => e.target.blur()} // <--- Adding this
            popperModifiers={{
              flip: {
                behavior: editPopupDate ? ["bottom"] : false, // don't allow it to flip to be above
              },
              preventOverflow: {
                enabled: false, // tell it not to try to stay within the view (this prevents the popper from covering the element you clicked)
              },
              hide: {
                enabled: false, // turn off since needs preventOverflow to be enabled
              },
            }}
            withPortal={withPortal}
            placeholderText={placeholderText}
          />
        </>
      )}
    </>
  );
};

export default DateInputTwo;
