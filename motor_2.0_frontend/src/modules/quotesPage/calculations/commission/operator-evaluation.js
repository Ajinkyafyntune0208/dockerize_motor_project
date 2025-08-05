import _ from "lodash";

const handleKeysMappedWithValues = (operator, mappedValue, filter_value) => {
  switch (operator) {
    case "EQUALS":
      //check for array
      if (Array.isArray(mappedValue)) {
        //Iterate Over Mapped Value.
        return !_.isEmpty(mappedValue) && mappedValue.includes(filter_value);
      } else {
        return filter_value === mappedValue;
      }
    case "EXCLUDED":
      //check for array
      if (Array.isArray(mappedValue)) {
        //Iterate Over Mapped Value.
        return !_.isEmpty(mappedValue) && !mappedValue.includes(filter_value);
      } else {
        return filter_value !== mappedValue;
      }
    case "INCLUDED":
      //check for array
      if (Array.isArray(mappedValue)) {
        //Iterate Over Mapped Value.
        return !_.isEmpty(mappedValue) && mappedValue.includes(filter_value);
      } else {
        return filter_value === mappedValue;
      }
  }
};

export const handleOperators = (field_slug, operator, value, KeyMapping) => {
  if (!_.isEmpty(value)) {
    switch (operator) {
      case "RANGE":
        //Values can have multiple ranges.
        let qualifyingRange = [];
        value.forEach(({ max_range, min_range }) => {
          qualifyingRange.push(
            +min_range > KeyMapping?.[`${field_slug}`] &&
            +max_range < KeyMapping?.[`${field_slug}`]
          );
        });
        return !qualifyingRange.includes(false) && !_.isEmpty(qualifyingRange);
      case "EQUALS":
        let qualifyingEquals = [];
        value.forEach(({ filter_value }) => {
          //Check whether mapped key has data type array or not
          qualifyingEquals.push(
            handleKeysMappedWithValues(
              operator,
              KeyMapping?.[`${field_slug}`],
              filter_value
            )
          );
        });
        return (
          !qualifyingEquals.includes(false) && !_.isEmpty(qualifyingEquals)
        );
      case "EXCLUDED":
        let qualifyingExclude = [];
        value.forEach(({ filter_value }) => {
          qualifyingExclude.push(
            handleKeysMappedWithValues(
              operator,
              KeyMapping?.[`${field_slug}`],
              filter_value
            )
          );
        });
        return (
          !qualifyingExclude.includes(false) && !_.isEmpty(qualifyingExclude)
        );
      case "INCLUDED":
        let qualifyingInclude = [];
        value.forEach(({ filter_value }) => {
          qualifyingInclude.push(
            handleKeysMappedWithValues(
              operator,
              KeyMapping?.[`${field_slug}`],
              filter_value
            )
          );
        });
        return (
          !qualifyingInclude.includes(false) && !_.isEmpty(qualifyingInclude)
        );
      case "CONTAINS":
        let qualifyingContains = [];
        value.forEach(({ filter_value }) => {
          qualifyingContains.push(
            !!KeyMapping?.[`${field_slug}`] && KeyMapping?.[`${field_slug}`].includes(filter_value)
          );
        });
        return (
          !qualifyingContains.includes(false) && !_.isEmpty(qualifyingContains)
        );
      case "LESSTHAN":
        let qualifyingLessThan = [];
        value.forEach(({ filter_value }) => {
          qualifyingLessThan.push(
            +filter_value < +KeyMapping?.[`${field_slug}`]
          );
        });
        return (
          !qualifyingLessThan.includes(false) && !_.isEmpty(qualifyingLessThan)
        );
      case "GREATERTHAN":
        let qualifyingMoreThan = [];
        value.forEach(({ filter_value }) => {
          qualifyingMoreThan.push(
            +filter_value > +KeyMapping?.[`${field_slug}`]
          );
        });
        return (
          !qualifyingMoreThan.includes(false) && !_.isEmpty(qualifyingMoreThan)
        );
      case "STARTSWITH":
        let qualifyingStartswith = [];
        value.forEach(({ filter_value }) => {
          qualifyingStartswith.push(
            !!KeyMapping?.[`${field_slug}`] && KeyMapping?.[`${field_slug}`].startsWith(filter_value)
          );
        });
        return (
          !qualifyingStartswith.includes(false) &&
          !_.isEmpty(qualifyingStartswith)
        );
      case "ENDSWITH":
        let qualifyingEndswith = [];
        value.forEach(({ filter_value }) => {
          qualifyingEndswith.push(
            !!KeyMapping?.[`${field_slug}`] && KeyMapping?.[`${field_slug}`].endsWith(filter_value)
          );
        });
        return (
          !qualifyingEndswith.includes(false) && !_.isEmpty(qualifyingEndswith)
        );
      default:
        return false;
    }
  }
  else {
    return false
  }
};
