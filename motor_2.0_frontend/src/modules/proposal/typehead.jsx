import React, { Fragment } from "react";
import { AsyncTypeahead } from "react-bootstrap-typeahead";
import "react-bootstrap-typeahead/css/Typeahead.css";
import _ from "lodash";

export const SearchInput = ({
  handleSearch,
  options,
  register,
  name,
  Controller,
  control,
  defaultValue,
  prefillData,
  selected,
  allowNew,
  newSelectionPrefix,
  multiple,
  isEmail,
  defaultInputValue
}) => {
  const formatted_options = !_.isEmpty(options) ? options : [];
  // filtered by the search endpoint, so no need to do it again.
  const filterBy = () => true;

  return (
    <>
      <Controller
        as={
          <AsyncTypeahead
            allowNew={allowNew}
            filterBy={filterBy}
            newSelectionPrefix={newSelectionPrefix}
            id="custom-selections-example"
            name={name}
            ref={register}
            labelKey="name"
            minLength={3}
            onSearch={handleSearch}
            clearButton={true}
            defaultSelected={selected ? selected : ""}
            defaultInputValue={defaultInputValue ? defaultInputValue : ""}
            options={formatted_options}
            placeholder={isEmail ? "Email" : "Search financer..."}
            multiple={multiple}
            renderMenuItemChildren={(option, props) => (
              <Fragment>
                <span style={{ fontSize: "12px" }}>
                  {(option?.name).trim()}
                </span>
              </Fragment>
            )}
          />
        }
        defaultValue={defaultValue}
        name={name}
        control={control}
      />
    </>
  );
};